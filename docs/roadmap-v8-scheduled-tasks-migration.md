# Roadmap V8 - Migração de Comandos Agendados (Scheduled Tasks)

**Status:** 🔄 Planejamento
**Dependências:** V1-V7 (completos)
**Objetivo:** Migrar comandos agendados do Laravel (datagrana-web) para Node.js/Fastify com jobs resilientes e monitoramento

**Última atualização:** Investigação profunda da implementação legado concluída

---

## 📋 Visão Geral

O projeto legado (`datagrana-web`) possui **8 comandos agendados críticos** para:
- Atualização de preços de tickers (ações, FIIs, ETFs, BDRs)
- Sincronização de lista de ativos da B3
- Crawler de dividendos de FIIs
- Renovação automática de assinaturas
- Reativação inteligente de tickers inativos

Esta versão migra esses jobs para o novo sistema Node.js/Fastify usando **Bull** (queue) + **BullMQ** (otimizado) + **BullBoard** (monitoramento) + **node-cron** (scheduler).

### Arquitetura Proposta

```
Scheduler (node-cron)
├── Dispara jobs em horários específicos
├── Valida janelas de execução (horário de pregão, dias úteis)
└── Enfileira tasks no Bull Queue

Bull Queue (Redis)
├── Processa jobs de forma assíncrona
├── Retry automático com backoff exponencial
├── Rate limiting por fonte de dados
├── Priorização dinâmica de jobs
└── Logs estruturados com contexto completo

BullBoard Dashboard
├── Monitoramento visual de jobs em tempo real
├── Métricas de sucesso/falha por job
├── Retry manual de jobs falhos
├── Histórico de execuções (últimos 7 dias)
└── Gráficos de performance

Fallback Strategy
├── M_Finance (primário) → Brapi (fallback) → Alpha Vantage (último recurso)
└── Desativa ticker apenas após 3 tentativas falhadas
```

---

## 🎯 Comandos a Migrar (Análise Detalhada)

### 1. Atualização de Preços (M_Finance)

#### Comando Legado
```php
// Laravel Command
app:update-mfinance-ticker-prices --only-active --stale-minutes=30 --limit=50
```

#### Análise da Implementação Atual

**Arquivo:** `app/Console/Commands/MFinance/UpdateTickerPricesCommand.php`
**Service:** `app/Services/External/MFinance/MFinanceTickerPriceUpdater.php`

**Lógica Detalhada:**

1. **Validação de Janela de Execução:**
   ```php
   // Executa apenas em dias úteis (seg-sex) entre 08:00-18:00
   $dayOfWeek >= 1 && $dayOfWeek <= 5 && $hour > 7 && $hour < 19
   ```

2. **Seleção de Tickers Elegíveis:**
   ```sql
   SELECT * FROM company_tickers
   WHERE status = 1
   AND can_update = 1
   AND (last_price_updated IS NULL OR last_price_updated <= NOW() - INTERVAL 30 MINUTE)
   AND EXISTS (
     SELECT 1 FROM consolidated
     WHERE ticker_id = company_tickers.id
     AND quantity_current > 0
     AND (closed = false OR closed IS NULL)
   )
   ORDER BY COALESCE(last_price_updated, '1970-01-01') ASC
   LIMIT 50
   ```

3. **Mapeamento de Categorias para M_Finance:**
   ```php
   $type_m_finance = [
     'ACAO' => 'stock',
     'FII' => 'fii',
     'ETF' => 'etf',
     'BDR' => null // BDR usa fallback (Brapi)
   ];
   ```

4. **Estratégia de Fallback:**
   - **M_Finance retorna 404** → Tenta Brapi
   - **Preço = 0 ou null** → Marca `can_update = 0` (desativa ticker)
   - **M_Finance falha** → Tenta Brapi
   - **Ambos falham** → Log de erro + contador de falhas

5. **Atualização de Dados:**
   ```php
   // Atualiza ticker
   company_tickers.last_price = $price
   company_tickers.last_price_updated = NOW()

   // Atualiza company (se vazio)
   companies.name = $payload['companyName']
   companies.nickname = $payload['shortName']
   companies.photo = $payload['logo']

   // Cria/atualiza closing (se último dia útil do mês)
   company_closings.price = $price
   company_closings.open = $open
   company_closings.high = $high
   company_closings.low = $low
   company_closings.volume = $volume
   company_closings.previous_close = $previousClose
   company_closings.date = TODAY()
   ```

6. **Detecção de Último Dia Útil:**
   ```php
   private function isLastBusinessDayOfMonth(Carbon $date): bool {
     $nextBusinessDay = $date->copy()->addDay();
     while ($nextBusinessDay->isWeekend()) {
       $nextBusinessDay->addDay();
     }
     return $nextBusinessDay->month !== $date->month;
   }
   ```

#### Nova Implementação (Node.js)

**Job:** `UpdateMFinancePricesJob`
**Queue:** `price-updates`
**Prioridade:** Alta (1)
**Retry:** 2 tentativas com backoff (30s, 2min)
**Rate Limit:** 50 requests/minuto
**Timeout:** 5 minutos

```typescript
// jobs/price/UpdateMFinancePricesJob.ts
import { Job } from '../base/Job.interface';
import { MFinanceService } from '../../services/external/MFinanceService';
import { BrapiService } from '../../services/external/BrapiService';
import { TickerRepository } from '../../repositories/TickerRepository';
import { TickerPriceRepository } from '../../repositories/TickerPriceRepository';
import { CompanyClosingRepository } from '../../repositories/CompanyClosingRepository';
import { isBusinessDay, isTradingHours, isLastBusinessDayOfMonth } from '../../utils/tradingHours';

export class UpdateMFinancePricesJob implements Job {
  name = 'UpdateMFinancePrices';
  queue = 'price-updates';
  priority = 1;
  attempts = 2;
  backoff = { type: 'exponential' as const, delay: 30000 };

  private readonly CATEGORY_MAPPING: Record<string, string> = {
    'ACAO': 'stock',
    'FII': 'fii',
    'ETF': 'etf',
  };

  async execute(data: { onlyActive: boolean; staleMinutes: number; limit: number; force?: boolean }) {
    const { onlyActive, staleMinutes, limit, force = false } = data;

    // Validar janela de execução
    if (!force && (!isBusinessDay() || !isTradingHours())) {
      console.log(`[${this.name}] Outside trading window (Mon-Fri 08:00-18:00)`);
      return;
    }

    console.log(`[${this.name}] Starting price update...`);

    // 1. Buscar tickers elegíveis
    const tickers = await TickerRepository.getStaleTickers({
      onlyActive,
      staleMinutes,
      limit,
    });

    if (tickers.length === 0) {
      console.log(`[${this.name}] No stale tickers found`);
      return;
    }

    console.log(`[${this.name}] Found ${tickers.length} tickers to update`);

    const summary = {
      total: tickers.length,
      success: 0,
      disabled: 0,
      failed: 0,
      details: [] as any[],
    };

    // 2. Processar cada ticker
    for (const ticker of tickers) {
      try {
        // BDR usa fallback direto (M_Finance não suporta)
        if (ticker.company.category.reference === 'BDR') {
          await this.processFallback(ticker, summary);
          continue;
        }

        // Mapear categoria para segmento M_Finance
        const segment = this.CATEGORY_MAPPING[ticker.company.category.reference];

        if (!segment) {
          console.log(`[${this.name}] Unknown category for ${ticker.code}, using fallback`);
          await this.processFallback(ticker, summary);
          continue;
        }

        // Buscar preço no M_Finance
        const response = await MFinanceService.getQuote(segment, ticker.code);

        if (!response.success) {
          if (response.statusCode === 404) {
            // Ticker não encontrado → fallback
            await this.processFallback(ticker, summary);
          } else {
            // Erro temporário
            summary.failed++;
            summary.details.push({
              ticker: ticker.code,
              status: 'failed',
              error: response.error,
            });
          }
          continue;
        }

        // Validar dados recebidos
        const price = this.extractPrice(response.data);

        if (!price || price === 0) {
          // Preço inválido → desativar ticker
          await TickerRepository.disable(ticker.id);
          summary.disabled++;
          summary.details.push({
            ticker: ticker.code,
            status: 'disabled',
            reason: 'Price unavailable or zero',
          });
          continue;
        }

        // 3. Atualizar ticker
        await TickerRepository.updatePrice(ticker.id, {
          lastPrice: price,
          lastPriceUpdated: new Date(),
        });

        // 4. Atualizar company metadata (se vazio)
        if (!ticker.company.name || !ticker.company.photo) {
          await this.updateCompanyMeta(ticker.company.id, response.data);
        }

        // 5. Criar/atualizar closing (se último dia útil do mês)
        if (isLastBusinessDayOfMonth()) {
          await this.syncClosing(ticker.id, response.data);
        }

        summary.success++;
        summary.details.push({
          ticker: ticker.code,
          status: 'success',
          price,
        });

        console.log(`[${this.name}] ✓ ${ticker.code}: ${price}`);
      } catch (error: any) {
        summary.failed++;
        summary.details.push({
          ticker: ticker.code,
          status: 'failed',
          error: error.message,
        });
        console.error(`[${this.name}] Error processing ${ticker.code}:`, error.message);
      }
    }

    console.log(`[${this.name}] Summary:`, {
      total: summary.total,
      success: summary.success,
      disabled: summary.disabled,
      failed: summary.failed,
    });

    return summary;
  }

  private async processFallback(ticker: any, summary: any) {
    try {
      const brapiResponse = await BrapiService.getQuote(ticker.code);

      if (!brapiResponse.success) {
        summary.failed++;
        summary.details.push({
          ticker: ticker.code,
          status: 'failed_fallback',
          error: 'Both M_Finance and Brapi failed',
        });
        return;
      }

      const price = this.extractPrice(brapiResponse.data);

      if (!price || price === 0) {
        await TickerRepository.disable(ticker.id);
        summary.disabled++;
        summary.details.push({
          ticker: ticker.code,
          status: 'disabled',
          reason: 'Price unavailable (fallback)',
        });
        return;
      }

      await TickerRepository.updatePrice(ticker.id, {
        lastPrice: price,
        lastPriceUpdated: new Date(),
      });

      summary.success++;
      summary.details.push({
        ticker: ticker.code,
        status: 'success_fallback',
        source: 'brapi',
        price,
      });

      console.log(`[${this.name}] ✓ ${ticker.code}: ${price} (fallback)`);
    } catch (error: any) {
      summary.failed++;
      summary.details.push({
        ticker: ticker.code,
        status: 'failed_fallback',
        error: error.message,
      });
    }
  }

  private extractPrice(data: any): number | null {
    const keys = ['lastPrice', 'price', 'close', 'regularMarketPrice'];

    for (const key of keys) {
      if (data[key] && typeof data[key] === 'number') {
        return parseFloat(data[key].toFixed(8));
      }
    }

    return null;
  }

  private async updateCompanyMeta(companyId: number, data: any) {
    const update: any = {};

    if (data.companyName && !update.name) {
      update.name = data.companyName.trim();
    }

    if (data.shortName && !update.nickname) {
      update.nickname = data.shortName.trim();
    }

    if (data.logo && !update.photo) {
      update.photo = data.logo;
    }

    if (Object.keys(update).length > 0) {
      await CompanyRepository.update(companyId, update);
    }
  }

  private async syncClosing(tickerId: number, data: any) {
    const price = this.extractPrice(data);
    const open = data.priceOpen || data.open || price;
    const high = data.high || data.dayHigh || data.max || price;
    const low = data.low || data.dayLow || data.min || price;
    const previousClose = data.closingPrice || data.previousClose;
    const volume = data.volume || 0;

    await CompanyClosingRepository.upsert({
      tickerId,
      date: new Date().toISOString().split('T')[0],
      open,
      high,
      low,
      price,
      volume,
      previousClose,
    });
  }

  async onComplete(result: any) {
    console.log(`[${this.name}] Job completed:`, result);
  }

  async onFailed(error: Error) {
    console.error(`[${this.name}] Job failed:`, error.message);
    // Enviar alerta via Sentry/email
  }
}
```

---

### 2. Sincronização de Lista de Ativos B3 (Brapi)

#### Comando Legado
```php
app:sync-brapi-stock-list --limit=100 --pages=999
```

#### Análise da Implementação Atual

**Arquivo:** `app/Console/Commands/Brapi/SyncStockListCommand.php`
**Service:** `app/Services/External/Brapi/StockListSynchronizer.php`

**Lógica Detalhada:**

1. **Busca Paginada da API Brapi:**
   ```php
   // GET https://brapi.dev/api/quote/list?limit=100&page=1
   // Itera até pages=999 ou até não ter mais resultados
   ```

2. **Processamento de Cada Ativo:**
   ```php
   foreach ($stocks as $stock) {
     // 1. Buscar/criar company pelo código
     $company = Company::firstOrCreate(
       ['code' => $stock['stock']],
       [
         'name' => $stock['name'],
         'nickname' => $stock['name'],
         'category_id' => $this->resolveCategoryId($stock['type']),
         'coin_id' => $this->resolveCoinId($stock['stock']),
         'status' => true,
       ]
     );

     // 2. Buscar/criar ticker
     $ticker = CompanyTicker::firstOrCreate(
       ['company_id' => $company->id, 'code' => $stock['stock']],
       [
         'status' => true,
         'can_update' => true,
       ]
     );

     // 3. Atualizar metadata se mudou
     if ($ticker->wasRecentlyCreated) {
       $summary['created_tickers']++;
     } else {
       $summary['updated_tickers']++;
     }
   }
   ```

3. **Inativação de Tickers Ausentes:**
   ```php
   // Marca tickers que não vieram na API como inativos
   $activeCodes = $allStocksFromApi->pluck('stock')->toArray();

   CompanyTicker::whereNotIn('code', $activeCodes)
     ->update(['status' => false]);
   ```

4. **Logs Detalhados:**
   ```php
   // Gera arquivo de log com timestamp
   storage/logs/sync-brapi-stock-list_2024-01-15_08-30-00.log
   ```

#### Nova Implementação (Node.js)

**Job:** `SyncBrapiStockListJob`
**Queue:** `stock-sync`
**Prioridade:** Alta
**Retry:** 3 tentativas (1min, 5min, 15min)
**Rate Limit:** 100 requests/minuto

```typescript
// jobs/stock/SyncBrapiStockListJob.ts
export class SyncBrapiStockListJob implements Job {
  name = 'SyncBrapiStockList';
  queue = 'stock-sync';
  priority = 1;
  attempts = 3;
  backoff = { type: 'exponential' as const, delay: 60000 };

  async execute(data: { limit: number; pages: number }) {
    const { limit, pages } = data;

    console.log(`[${this.name}] Starting sync (limit=${limit}, pages=${pages})`);

    const summary = {
      processed: 0,
      created_tickers: 0,
      updated_tickers: 0,
      created_companies: 0,
      updated_companies: 0,
      deactivated_tickers: 0,
    };

    const allActiveCodes: string[] = [];
    let currentPage = 1;
    let hasMore = true;

    // 1. Iterar pelas páginas da API
    while (hasMore && currentPage <= pages) {
      try {
        const response = await BrapiService.getStockList(limit, currentPage);

        if (!response.success || !response.data?.stocks?.length) {
          hasMore = false;
          break;
        }

        const stocks = response.data.stocks;
        console.log(`[${this.name}] Page ${currentPage}: ${stocks.length} stocks`);

        // 2. Processar cada ativo
        for (const stock of stocks) {
          allActiveCodes.push(stock.stock);

          // Resolver categoria e moeda
          const categoryId = await this.resolveCategoryId(stock.type);
          const coinId = await this.resolveCoinId(stock.stock);

          // Buscar ou criar company
          let company = await CompanyRepository.findByCode(stock.stock);

          if (!company) {
            company = await CompanyRepository.create({
              code: stock.stock,
              name: stock.name,
              nickname: stock.name,
              categoryId,
              coinId,
              status: true,
            });
            summary.created_companies++;
          } else {
            // Atualizar se necessário
            const updates: any = {};
            if (!company.name) updates.name = stock.name;
            if (!company.nickname) updates.nickname = stock.name;

            if (Object.keys(updates).length > 0) {
              await CompanyRepository.update(company.id, updates);
              summary.updated_companies++;
            }
          }

          // Buscar ou criar ticker
          let ticker = await TickerRepository.findByCompanyAndCode(company.id, stock.stock);

          if (!ticker) {
            ticker = await TickerRepository.create({
              companyId: company.id,
              code: stock.stock,
              status: true,
              canUpdate: true,
            });
            summary.created_tickers++;
          } else {
            // Reativar se estava inativo
            if (!ticker.status) {
              await TickerRepository.update(ticker.id, { status: true });
              summary.updated_tickers++;
            }
          }

          summary.processed++;
        }

        currentPage++;

        // Rate limiting
        await this.delay(60); // 60ms entre requisições

      } catch (error: any) {
        console.error(`[${this.name}] Error on page ${currentPage}:`, error.message);
        throw error;
      }
    }

    // 3. Inativar tickers que não vieram na API
    const deactivated = await TickerRepository.deactivateNotIn(allActiveCodes);
    summary.deactivated_tickers = deactivated;

    console.log(`[${this.name}] Summary:`, summary);

    // 4. Salvar log detalhado
    await this.saveDetailedLog(summary);

    return summary;
  }

  private async resolveCategoryId(type: string): Promise<number> {
    const mapping: Record<string, string> = {
      'stock': 'ACAO',
      'fund': 'FII',
      'bdr': 'BDR',
      'etf': 'ETF',
    };

    const reference = mapping[type.toLowerCase()] || 'ACAO';
    const category = await CategoryRepository.findByReference(reference);
    return category.id;
  }

  private async resolveCoinId(code: string): Promise<number> {
    // BDRs terminam com 34 ou 35 → USD
    const isUSD = /3[45]$/.test(code);
    const currencyCode = isUSD ? 'USD' : 'BRL';
    const coin = await CoinRepository.findByCode(currencyCode);
    return coin.id;
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  private async saveDetailedLog(summary: any) {
    const timestamp = new Date().toISOString().replace(/:/g, '-').split('.')[0];
    const logPath = `logs/sync-brapi-stock-list_${timestamp}.log`;
    const content = JSON.stringify(summary, null, 2);
    await fs.writeFile(logPath, content);
  }
}
```

---

### 3. Reativação de Tickers

#### Comando Legado
```php
app:reactivate-tickers --limit=100 --cooldown=45 --stale-minutes=120
```

#### Análise da Implementação Atual

**Arquivo:** `app/Console/Commands/Tickers/ReactivateTickersCommand.php`

**Lógica Detalhada:**

1. **Seleção de Tickers Desativados:**
   ```sql
   SELECT * FROM company_tickers
   WHERE can_update = 0
   AND status = 1
   AND (updated_at IS NULL OR updated_at <= NOW() - INTERVAL 45 MINUTE)
   ORDER BY COALESCE(last_price_updated, updated_at, created_at) ASC
   LIMIT 100
   ```

2. **Reativação com Ajuste de Timestamp:**
   ```php
   // Calcula baseline para recolocar no fim da fila
   $adjustment = max($staleMinutes - $cooldown, 0);
   $baseline = Carbon::now()->subMinutes($adjustment);

   foreach ($tickers as $ticker) {
     $ticker->can_update = 1;
     $ticker->last_price_updated = $baseline; // Coloca no fim da fila
     $ticker->save();
   }
   ```

   **Exemplo:**
   - `staleMinutes = 120` (tickers desatualizados há mais de 2h entram na fila)
   - `cooldown = 45` (aguarda 45min antes de reativar)
   - `adjustment = 120 - 45 = 75`
   - `baseline = NOW() - 75min`
   - Resultado: ticker reativado entrará na fila após ~45 minutos

#### Nova Implementação (Node.js)

**Job:** `ReactivateTickersJob`
**Queue:** `ticker-maintenance`
**Prioridade:** Média

```typescript
// jobs/ticker/ReactivateTickersJob.ts
export class ReactivateTickersJob implements Job {
  name = 'ReactivateTickers';
  queue = 'ticker-maintenance';
  priority = 2;
  attempts = 1;

  async execute(data: { limit: number; cooldownMinutes: number; staleMinutes: number }) {
    const { limit, cooldownMinutes, staleMinutes } = data;

    console.log(`[${this.name}] Finding disabled tickers (cooldown=${cooldownMinutes}min)`);

    // 1. Buscar tickers desativados que aguardaram cooldown
    const threshold = new Date(Date.now() - cooldownMinutes * 60 * 1000);

    const tickers = await TickerRepository.findDisabledWithCooldown({
      threshold,
      limit,
    });

    if (tickers.length === 0) {
      console.log(`[${this.name}] No tickers eligible for reactivation`);
      return { reactivated: 0 };
    }

    console.log(`[${this.name}] Found ${tickers.length} tickers to reactivate`);

    // 2. Calcular baseline para recolocar no fim da fila
    const adjustment = Math.max(staleMinutes - cooldownMinutes, 0);
    const baseline = new Date(Date.now() - adjustment * 60 * 1000);

    // 3. Reativar tickers
    let reactivated = 0;

    for (const ticker of tickers) {
      await TickerRepository.update(ticker.id, {
        canUpdate: true,
        lastPriceUpdated: baseline,
      });
      reactivated++;
    }

    const codes = tickers.map(t => t.code).join(', ');

    console.log(`[${this.name}] Reactivated ${reactivated} tickers: ${codes}`);

    return { reactivated, tickers: codes };
  }
}
```

---

### 4. Crawler de Dividendos de FIIs

#### Comando Legado
```php
app:crawl-fii-dividends --headless
```

#### Análise da Implementação Atual

**Arquivo:** `app/Console/Commands/Crawler/CrawlFiiDividends.php`
**Tecnologia:** Symfony Panther (Chrome headless via Selenium)

**Lógica Detalhada:**

1. **Seleção de FIIs a Processar:**
   ```sql
   SELECT DISTINCT ct.* FROM company_tickers ct
   INNER JOIN consolidated c ON c.ticker_id = ct.id
   INNER JOIN companies co ON co.id = ct.company_id
   INNER JOIN company_categories cc ON cc.id = co.category_id
   WHERE c.closed = false
   AND ct.can_update = 1
   AND co.status = true
   AND cc.reference = 'FII'
   ```

2. **Janela de Atualização (Smart Update):**
   ```php
   // Atualiza apenas se:
   // 1. Nunca foi atualizado (last_earnings_updated IS NULL)
   // 2. Passou 30 dias desde última approved_date do último dividendo
   // 3. Não foi atualizado hoje

   $lastEarning = CompanyEarning::where('ticker_id', $ticker->id)
     ->orderBy('approved_date', 'desc')
     ->first();

   if (!$lastEarning) {
     return true; // Sem histórico → atualizar
   }

   $nextWindow = Carbon::parse($lastEarning->approved_date)->addDays(30);
   $today = now();

   return $today->gte($nextWindow);
   ```

3. **Scraping (Investidor10):**
   ```php
   $url = "https://investidor10.com.br/fiis/" . strtolower($ticker);
   $crawler = $this->client->request('GET', $url);

   // Aguarda carregamento
   sleep(3);

   // Navega por todas as páginas (max 20)
   while ($currentPage <= 20) {
     $rows = $crawler->filter('#table-dividends-history tbody tr');

     $dividends = $rows->each(function ($node) {
       $cells = $node->filter('td');
       return [
         'type' => $cells->eq(0)->text(),
         'com_date' => parseDate($cells->eq(1)->text()),
         'payment_date' => parseDate($cells->eq(2)->text()),
         'value_per_quota' => parseValue($cells->eq(3)->text()),
       ];
     });

     // Clica em "Próximo" se disponível
     $nextButtonDisabled = $this->client->executeScript(
       "return document.querySelector('#table-dividends-history_next').classList.contains('disabled');"
     );

     if ($nextButtonDisabled) break;

     $this->client->executeScript(
       "document.querySelector('#table-dividends-history_next').click();"
     );

     sleep(2); // Aguarda carregamento da próxima página
     $currentPage++;
   }
   ```

4. **Salvamento no Banco:**
   ```php
   foreach ($dividends as $dividend) {
     // Verifica se já existe (evita duplicados)
     $existing = CompanyEarning::where('ticker_id', $ticker->id)
       ->where('earning_type_id', $earningTypeId) // REN (Rendimento)
       ->where('approved_date', $dividend['com_date'])
       ->where('payment_date', $dividend['payment_date'])
       ->first();

     if ($existing) {
       // Atualiza apenas se o valor mudou
       if ($existing->value != $dividend['value_per_quota']) {
         $existing->update(['value' => $dividend['value_per_quota']]);
       }
     } else {
       // Cria novo registro
       CompanyEarning::create([
         'ticker_id' => $ticker->id,
         'earning_type_id' => $earningTypeId,
         'origin' => 'crawler_investidor10',
         'value' => $dividend['value_per_quota'],
         'approved_date' => $dividend['com_date'],
         'payment_date' => $dividend['payment_date'],
         'status' => true,
       ]);
     }
   }

   // Atualiza timestamp
   $ticker->update(['last_earnings_updated' => now()]);
   ```

5. **Rate Limiting:**
   ```php
   // Delay entre FIIs: 2-5 segundos (random)
   usleep(rand(2000000, 5000000));

   // Rate limit: ~20 requests/minuto (1 FII a cada 3 segundos)
   ```

6. **User Agent Rotation:**
   ```php
   $agents = [
     'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ...',
     'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) ...',
     'Mozilla/5.0 (X11; Linux x86_64) ...',
   ];
   $agent = $agents[array_rand($agents)];
   ```

#### Nova Implementação (Node.js)

**Job:** `CrawlFiiDividendsJob`
**Queue:** `dividend-crawler`
**Prioridade:** Média
**Retry:** 3 tentativas (5min, 30min, 2h)
**Rate Limit:** 20 requests/minuto
**Timeout:** 30 minutos
**Tecnologia:** Puppeteer (substitui Panther)

```typescript
// jobs/dividend/CrawlFiiDividendsJob.ts
import puppeteer, { Browser, Page } from 'puppeteer';

export class CrawlFiiDividendsJob implements Job {
  name = 'CrawlFiiDividends';
  queue = 'dividend-crawler';
  priority = 2;
  attempts = 3;
  backoff = { type: 'exponential' as const, delay: 300000 }; // 5 minutos
  timeout = 1800000; // 30 minutos

  private browser: Browser | null = null;

  async execute(data: { headless?: boolean; ticker?: string }) {
    const { headless = true, ticker } = data;

    console.log(`[${this.name}] Starting crawler (headless=${headless})`);

    try {
      // 1. Inicializar browser
      this.browser = await puppeteer.launch({
        headless,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-gpu',
          '--window-size=1920,1080',
        ],
      });

      // 2. Processar ticker específico ou múltiplos
      if (ticker) {
        await this.crawlSingleFii(ticker);
      } else {
        await this.crawlMultipleFiis();
      }

      return { success: true };
    } finally {
      if (this.browser) {
        await this.browser.close();
      }
    }
  }

  private async crawlMultipleFiis() {
    // Buscar FIIs do consolidated
    const fiis = await TickerRepository.findActiveFiis();

    console.log(`[${this.name}] Found ${fiis.length} FIIs in portfolio`);

    const stats = {
      success: 0,
      failed: 0,
      skipped_today: 0,
      skipped_window: 0,
      total_dividends: 0,
    };

    // Filtrar FIIs que precisam atualizar
    const fiisToUpdate = [];

    for (const fii of fiis) {
      const shouldUpdate = await this.shouldUpdateFii(fii);

      if (shouldUpdate.should_update) {
        fiisToUpdate.push(fii);
      } else {
        if (shouldUpdate.reason.includes('Já atualizado')) {
          stats.skipped_today++;
        } else {
          stats.skipped_window++;
        }
      }
    }

    console.log(`[${this.name}] FIIs to update: ${fiisToUpdate.length}`);
    console.log(`[${this.name}] Skipped (updated today): ${stats.skipped_today}`);
    console.log(`[${this.name}] Skipped (waiting window): ${stats.skipped_window}`);

    if (fiisToUpdate.length === 0) {
      return stats;
    }

    // Processar cada FII com rate limiting
    for (let i = 0; i < fiisToUpdate.length; i++) {
      const fii = fiisToUpdate[i];

      try {
        const result = await this.crawlSingleFii(fii.code);

        if (result.success) {
          stats.success++;
          stats.total_dividends += result.dividends_saved || 0;

          // Atualizar timestamp
          await TickerRepository.update(fii.id, {
            lastEarningsUpdated: new Date(),
          });
        } else {
          stats.failed++;
        }
      } catch (error: any) {
        stats.failed++;
        console.error(`[${this.name}] Error processing ${fii.code}:`, error.message);
      }

      // Rate limiting: 1 FII a cada 3 segundos
      if (i < fiisToUpdate.length - 1) {
        await this.delay(3000);
      }
    }

    console.log(`[${this.name}] Stats:`, stats);
    return stats;
  }

  private async crawlSingleFii(ticker: string) {
    console.log(`[${this.name}] Crawling ${ticker}...`);

    // Buscar ticker no banco
    const companyTicker = await TickerRepository.findByCode(ticker);

    if (!companyTicker) {
      console.warn(`[${this.name}] Ticker ${ticker} not found in database`);
      return { success: false };
    }

    // Buscar earning type "Rendimento"
    const earningType = await EarningTypeRepository.findByKey('REN');

    if (!earningType) {
      console.error(`[${this.name}] Earning type 'Rendimento' not found`);
      return { success: false };
    }

    const page = await this.browser!.newPage();

    try {
      // Random user agent
      await page.setUserAgent(this.getRandomUserAgent());

      // Navegar para página do FII
      const url = `https://investidor10.com.br/fiis/${ticker.toLowerCase()}`;
      await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

      // Aguardar tabela carregar
      await page.waitForSelector('#table-dividends-history', { timeout: 10000 });

      const allDividends: any[] = [];
      let currentPage = 1;
      const maxPages = 20;

      // Navegar por todas as páginas
      while (currentPage <= maxPages) {
        console.log(`[${this.name}]   Page ${currentPage}...`);

        // Extrair dados da página atual
        const pageDividends = await page.evaluate(() => {
          const rows = document.querySelectorAll('#table-dividends-history tbody tr');
          const results: any[] = [];

          rows.forEach((row) => {
            const cells = row.querySelectorAll('td');

            if (cells.length >= 4) {
              results.push({
                type: cells[0].textContent?.trim() || '',
                com_date: cells[1].textContent?.trim() || '',
                payment_date: cells[2].textContent?.trim() || '',
                value: cells[3].textContent?.trim() || '',
              });
            }
          });

          return results;
        });

        console.log(`[${this.name}]   Found ${pageDividends.length} dividends`);
        allDividends.push(...pageDividends);

        // Verificar se existe próxima página
        const hasNext = await page.evaluate(() => {
          const nextButton = document.querySelector('#table-dividends-history_next');
          return nextButton && !nextButton.classList.contains('disabled');
        });

        if (!hasNext) {
          console.log(`[${this.name}]   Last page reached`);
          break;
        }

        // Clicar em próximo
        await page.click('#table-dividends-history_next');
        await this.delay(2000); // Aguardar carregamento

        currentPage++;
      }

      console.log(`[${this.name}]   Total: ${allDividends.length} dividends found`);

      // Salvar no banco
      let saved = 0;
      let skipped = 0;

      for (const dividend of allDividends) {
        const result = await this.saveDividend(companyTicker.id, earningType.id, {
          comDate: this.parseDate(dividend.com_date),
          paymentDate: this.parseDate(dividend.payment_date),
          value: this.parseValue(dividend.value),
        });

        if (result) {
          saved++;
        } else {
          skipped++;
        }
      }

      console.log(`[${this.name}] ✓ ${ticker}: Saved ${saved}, Skipped ${skipped}`);

      return { success: true, dividends_saved: saved };
    } catch (error: any) {
      console.error(`[${this.name}] Error crawling ${ticker}:`, error.message);
      return { success: false, error: error.message };
    } finally {
      await page.close();
    }
  }

  private async shouldUpdateFii(ticker: any): Promise<{ should_update: boolean; reason: string }> {
    // Já atualizado hoje?
    if (ticker.lastEarningsUpdated) {
      const today = new Date().toISOString().split('T')[0];
      const lastUpdate = new Date(ticker.lastEarningsUpdated).toISOString().split('T')[0];

      if (today === lastUpdate) {
        return {
          should_update: false,
          reason: `Já atualizado hoje às ${new Date(ticker.lastEarningsUpdated).toLocaleTimeString()}`,
        };
      }
    }

    // Buscar último dividendo
    const lastEarning = await EarningRepository.findLatestByTicker(ticker.id);

    if (!lastEarning) {
      return { should_update: true, reason: 'Sem histórico de dividendos' };
    }

    // Verificar janela de 30 dias
    const lastApprovedDate = new Date(lastEarning.approvedDate);
    const nextWindow = new Date(lastApprovedDate);
    nextWindow.setDate(nextWindow.getDate() + 30);

    const today = new Date();

    if (today >= nextWindow) {
      const daysSince = Math.floor((today.getTime() - lastApprovedDate.getTime()) / (1000 * 60 * 60 * 24));
      return {
        should_update: true,
        reason: `Janela atingida (último: ${lastApprovedDate.toLocaleDateString()}, ${daysSince} dias atrás)`,
      };
    }

    const daysRemaining = Math.floor((nextWindow.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    return {
      should_update: false,
      reason: `Aguardando janela (próxima: ${nextWindow.toLocaleDateString()}, faltam ${daysRemaining} dias)`,
    };
  }

  private async saveDividend(tickerId: number, earningTypeId: number, data: any): Promise<boolean> {
    try {
      // Verificar se já existe
      const existing = await EarningRepository.findByTickerAndDates(tickerId, earningTypeId, data.comDate, data.paymentDate);

      if (existing) {
        // Atualizar se valor mudou
        if (existing.value !== data.value) {
          await EarningRepository.update(existing.id, {
            value: data.value,
            origin: 'crawler_investidor10',
          });
          return true;
        }
        return false; // Já existe e não mudou
      }

      // Criar novo
      await EarningRepository.create({
        tickerId,
        earningTypeId,
        origin: 'crawler_investidor10',
        value: data.value,
        approvedDate: data.comDate,
        paymentDate: data.paymentDate,
        status: true,
      });

      return true;
    } catch (error: any) {
      console.error(`[${this.name}] Error saving dividend:`, error.message);
      return false;
    }
  }

  private parseDate(dateStr: string): string {
    // Converte "15/01/2024" para "2024-01-15"
    const parts = dateStr.trim().split('/');
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }

  private parseValue(valueStr: string): number {
    // Remove "R$", pontos e substitui vírgula por ponto
    return parseFloat(
      valueStr
        .replace('R$', '')
        .replace(/\./g, '')
        .replace(',', '.')
        .trim()
    );
  }

  private getRandomUserAgent(): string {
    const agents = [
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];
    return agents[Math.floor(Math.random() * agents.length)];
  }

  private delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
```

---

### 5. Renovação de Assinaturas

#### Comando Legado
```php
subscriptions:renew --days=7 --dry-run
```

#### Análise da Implementação Atual

**Arquivo:** `app/Console/Commands/ProcessSubscriptionRenewals.php`
**Service:** `app/Services/Gateway/GatewayService.php`

**Lógica Detalhada:**

1. **Seleção de Assinaturas a Renovar:**
   ```sql
   SELECT * FROM subscriptions s
   WHERE s.status IN ('active', 'trialing')
   AND s.auto_renew = true
   AND s.renews_at <= NOW() + INTERVAL 7 DAY
   AND NOT EXISTS (
     SELECT 1 FROM gateway_charges gc
     WHERE gc.subscription_id = s.id
     AND gc.status = 'pending'
     AND gc.charge_type = 'subscription'
   )
   ```

2. **Cálculo do Valor com Desconto:**
   ```php
   $fullPrice = $planPeriod->full_price ?? $planPeriod->price;
   $discountAmount = 0.0;

   if ($subscription->promotion) {
     if ($promotion->is_active && (!$promotion->ends_at || $promotion->ends_at->isFuture())) {
       if ($promotion->type === 'percentage') {
         $discountAmount = ($fullPrice * $promotion->value) / 100;
       } else {
         $discountAmount = $promotion->value;
       }
     }
   }

   $finalAmount = max(0.0, $fullPrice - $discountAmount);
   ```

3. **Criação de Cobrança:**
   ```php
   $chargeDto = new CreateChargeDTO(
     customerId: null,
     billingType: BillingTypeEnum::from($billingReference), // PIX, BOLETO, CREDIT_CARD
     chargeType: ChargeTypeEnum::SUBSCRIPTION,
     amount: $finalAmount,
     dueDate: $subscription->renews_at,
     description: "Renovação - {$planName}",
     externalReference: "renewal_sub_{$subscription->id}_{$date}",
     metadata: [
       'type' => 'renewal',
       'subscription_period' => $periodName,
       'full_price' => $fullPrice,
       'discount_amount' => $discountAmount,
     ],
   );

   // Envia para gateway de pagamento (Asaas)
   $gatewayCharge = $this->gatewayService
     ->forGateway($gateway)
     ->charges()
     ->create($chargeDto, $user, $subscriptionId);
   ```

4. **Integração com Gateway:**
   ```php
   // POST https://api.asaas.com/v3/payments
   {
     "customer": "...",
     "billingType": "BOLETO", // ou PIX, CREDIT_CARD
     "value": 19.90,
     "dueDate": "2024-02-01",
     "description": "Renovação - Investidor Iniciante",
     "externalReference": "renewal_sub_123_20240125"
   }
   ```

5. **Tratamento de Erros:**
   ```php
   try {
     DB::transaction(function () use ($subscription) {
       $this->processRenewal($subscription);
     });
   } catch (\Throwable $e) {
     Log::error('Erro ao processar renovação', [
       'subscription_id' => $subscription->id,
       'error' => $e->getMessage(),
     ]);

     // Não interrompe o processamento das outras assinaturas
   }
   ```

#### Nova Implementação (Node.js)

**Job:** `RenewSubscriptionsJob`
**Queue:** `subscription-management`
**Prioridade:** Alta
**Retry:** 5 tentativas (1h, 6h, 12h, 24h, 48h)

```typescript
// jobs/subscription/RenewSubscriptionsJob.ts
export class RenewSubscriptionsJob implements Job {
  name = 'RenewSubscriptions';
  queue = 'subscription-management';
  priority = 1;
  attempts = 5;
  backoff = { type: 'exponential' as const, delay: 3600000 }; // 1 hora

  async execute(data: { daysAhead: number; dryRun?: boolean }) {
    const { daysAhead, dryRun = false } = data;

    console.log(`[${this.name}] Processing renewals (${daysAhead} days ahead)${dryRun ? ' [DRY-RUN]' : ''}`);

    // 1. Buscar assinaturas que renovam nos próximos N dias
    const renewalDate = new Date();
    renewalDate.setDate(renewalDate.getDate() + daysAhead);

    const subscriptions = await SubscriptionRepository.findPendingRenewals({
      renewsAtBefore: renewalDate,
      status: ['active', 'trialing'],
      autoRenew: true,
      hasNoPendingCharge: true,
    });

    if (subscriptions.length === 0) {
      console.log(`[${this.name}] No subscriptions to renew`);
      return { processed: 0, errors: 0 };
    }

    console.log(`[${this.name}] Found ${subscriptions.length} subscriptions to renew`);

    const summary = {
      processed: 0,
      errors: 0,
      details: [] as any[],
    };

    // 2. Processar cada assinatura
    for (const subscription of subscriptions) {
      try {
        if (dryRun) {
          console.log(`[DRY-RUN] Would renew subscription #${subscription.id} for user ${subscription.userId}`);
          summary.processed++;
        } else {
          await this.processRenewal(subscription);
          summary.processed++;
        }

        summary.details.push({
          subscriptionId: subscription.id,
          userId: subscription.userId,
          status: 'success',
        });
      } catch (error: any) {
        summary.errors++;
        summary.details.push({
          subscriptionId: subscription.id,
          userId: subscription.userId,
          status: 'failed',
          error: error.message,
        });

        console.error(`[${this.name}] Error processing subscription #${subscription.id}:`, error.message);

        // Enviar alerta
        await AlertService.notifySubscriptionRenewalFailed(subscription.id, error);
      }
    }

    console.log(`[${this.name}] Summary:`, {
      total: subscriptions.length,
      processed: summary.processed,
      errors: summary.errors,
    });

    return summary;
  }

  private async processRenewal(subscription: any) {
    // 1. Calcular valor com desconto
    const fullPrice = subscription.planPeriod.fullPrice || subscription.planPeriod.price;
    let discountAmount = 0;

    if (subscription.promotion) {
      const promotion = subscription.promotion;

      if (promotion.isActive && (!promotion.endsAt || new Date(promotion.endsAt) > new Date())) {
        if (promotion.type === 'percentage') {
          discountAmount = (fullPrice * promotion.value) / 100;
        } else {
          discountAmount = promotion.value;
        }
      }
    }

    const finalAmount = Math.max(0, fullPrice - discountAmount);

    if (finalAmount <= 0) {
      console.log(`[${this.name}] Subscription #${subscription.id} has zero amount, skipping charge`);
      return;
    }

    // 2. Obter gateway ativo
    let gateway = subscription.gateway;

    if (!gateway || !gateway.isActive) {
      gateway = await GatewayRepository.findActiveGateway();
    }

    if (!gateway) {
      throw new Error('No active gateway found');
    }

    // 3. Mapear billing type
    const billingReference = subscription.planPeriod.gatewayBillingType?.billingType?.reference || subscription.planPeriod.billingType?.reference;

    const billingTypeMap: Record<string, string> = {
      pix: 'PIX',
      boleto: 'BOLETO',
      credit_card: 'CREDIT_CARD',
      debit_card: 'DEBIT_CARD',
    };

    const billingType = billingTypeMap[billingReference];

    if (!billingType) {
      throw new Error(`Invalid billing type: ${billingReference}`);
    }

    // 4. Criar cobrança
    const dueDate = subscription.renewsAt || new Date(Date.now() + 3 * 24 * 60 * 60 * 1000); // 3 dias

    const chargeData = {
      billingType,
      chargeType: 'SUBSCRIPTION',
      amount: finalAmount,
      dueDate,
      description: `Renovação - ${subscription.planPeriod.plan.name}`,
      externalReference: `renewal_sub_${subscription.id}_${new Date().toISOString().split('T')[0].replace(/-/g, '')}`,
      metadata: {
        type: 'renewal',
        subscription_period: subscription.planPeriod.subscriptionPeriod?.name,
        full_price: fullPrice,
        discount_amount: discountAmount,
      },
    };

    // 5. Enviar para gateway de pagamento
    const gatewayCharge = await GatewayService.createCharge({
      gateway,
      user: subscription.user,
      subscriptionId: subscription.id,
      chargeData,
    });

    console.log(`[${this.name}] ✓ Charge created for subscription #${subscription.id}:`, {
      chargeId: gatewayCharge.id,
      gatewayChargeId: gatewayCharge.gatewayChargeId,
      amount: finalAmount,
    });

    // 6. Enviar email de lembrete ao usuário
    await EmailService.sendSubscriptionRenewalReminder({
      user: subscription.user,
      subscription,
      charge: gatewayCharge,
      amount: finalAmount,
      dueDate,
    });
  }
}
```

---

## 🗄️ Estrutura de Código (Detalhada)

### Diretórios Completos

```
src/
├── jobs/
│   ├── base/
│   │   ├── Job.interface.ts
│   │   └── BaseJob.abstract.ts
│   ├── stock/
│   │   ├── SyncBrapiStockListJob.ts
│   │   └── ReactivateTickersJob.ts
│   ├── price/
│   │   ├── UpdateMFinancePricesJob.ts
│   │   ├── UpdateBrapiPricesJob.ts (backup)
│   │   └── UpdateAlphaVantagePricesJob.ts (fallback)
│   ├── dividend/
│   │   └── CrawlFiiDividendsJob.ts
│   └── subscription/
│       └── RenewSubscriptionsJob.ts
├── queues/
│   ├── QueueManager.ts
│   ├── queues.config.ts
│   └── processors/
│       ├── StockSyncProcessor.ts
│       ├── PriceUpdateProcessor.ts
│       ├── DividendCrawlerProcessor.ts
│       └── SubscriptionProcessor.ts
├── schedulers/
│   ├── CronScheduler.ts
│   ├── schedules.config.ts
│   └── TradingWindowValidator.ts
├── services/
│   ├── external/
│   │   ├── MFinanceService.ts
│   │   ├── BrapiService.ts
│   │   ├── AlphaVantageService.ts
│   │   └── GatewayService.ts (Asaas/RevenueCat)
│   ├── monitoring/
│   │   ├── BullBoardService.ts
│   │   ├── JobMetricsService.ts
│   │   └── AlertService.ts
│   └── email/
│       └── EmailService.ts
├── repositories/
│   ├── TickerRepository.ts
│   ├── TickerPriceRepository.ts
│   ├── CompanyRepository.ts
│   ├── CompanyClosingRepository.ts
│   ├── EarningRepository.ts
│   ├── EarningTypeRepository.ts
│   ├── SubscriptionRepository.ts
│   └── GatewayRepository.ts
├── utils/
│   ├── tradingHours.ts
│   ├── dateHelpers.ts
│   └── logger.ts
└── types/
    ├── Job.types.ts
    ├── Queue.types.ts
    └── External.types.ts
```

---

## 🔧 Utilidades Críticas

### Trading Window Validation

```typescript
// utils/tradingHours.ts
import { DateTime } from 'luxon';

const TIMEZONE = 'America/Sao_Paulo';

export function isBusinessDay(date: Date = new Date()): boolean {
  const dt = DateTime.fromJSDate(date).setZone(TIMEZONE);
  const dayOfWeek = dt.weekday; // 1 = Monday, 7 = Sunday
  return dayOfWeek >= 1 && dayOfWeek <= 5;
}

export function isTradingHours(date: Date = new Date()): boolean {
  const dt = DateTime.fromJSDate(date).setZone(TIMEZONE);
  const hour = dt.hour;
  return hour >= 8 && hour < 18; // 08:00 - 17:59
}

export function isLastBusinessDayOfMonth(date: Date = new Date()): boolean {
  const dt = DateTime.fromJSDate(date).setZone(TIMEZONE);
  let next = dt.plus({ days: 1 });

  // Pular fins de semana
  while (next.weekday > 5) {
    next = next.plus({ days: 1 });
  }

  return next.month !== dt.month;
}

export function getNextTradingDay(date: Date = new Date()): Date {
  let dt = DateTime.fromJSDate(date).setZone(TIMEZONE).plus({ days: 1 });

  while (dt.weekday > 5) {
    dt = dt.plus({ days: 1 });
  }

  return dt.toJSDate();
}
```

---

## 📊 Métricas e Monitoramento

### Estrutura de Logs de Jobs

```sql
-- Migration: create_job_execution_logs_table
CREATE TABLE job_execution_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Job info
  job_name VARCHAR(100) NOT NULL,
  queue_name VARCHAR(50) NOT NULL,
  job_id VARCHAR(191) NULL COMMENT 'Bull job ID',

  -- Status
  status ENUM('pending', 'active', 'completed', 'failed', 'delayed', 'stalled') NOT NULL,

  -- Timing
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  duration_ms INT NULL COMMENT 'Duração em milissegundos',

  -- Data
  input_data JSON NULL COMMENT 'Dados de entrada do job',
  output_data JSON NULL COMMENT 'Resultado da execução',
  error_message TEXT NULL,
  error_stack TEXT NULL,

  -- Retry
  attempt INT NOT NULL DEFAULT 1,
  max_attempts INT NULL,

  -- Metadata
  worker_id VARCHAR(100) NULL COMMENT 'ID do worker que processou',
  priority INT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_job_name (job_name),
  INDEX idx_queue_name (queue_name),
  INDEX idx_status (status),
  INDEX idx_started_at (started_at),
  INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Dashboard de Métricas

**Endpoint:** `GET /admin/jobs/metrics`

```typescript
// services/monitoring/JobMetricsService.ts
export class JobMetricsService {
  static async getMetrics(period: '1h' | '24h' | '7d' | '30d') {
    const since = this.getPeriodStart(period);

    // Agregações por job
    const jobStats = await db.query(`
      SELECT
        job_name,
        COUNT(*) as total_executions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        AVG(duration_ms) as avg_duration_ms,
        MAX(duration_ms) as max_duration_ms,
        MIN(duration_ms) as min_duration_ms
      FROM job_execution_logs
      WHERE created_at >= ?
      GROUP BY job_name
    `, [since]);

    // Taxa de sucesso global
    const globalStats = await db.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        (SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as success_rate
      FROM job_execution_logs
      WHERE created_at >= ?
    `, [since]);

    // Jobs mais lentos
    const slowestJobs = await db.query(`
      SELECT
        job_name,
        duration_ms,
        started_at,
        input_data
      FROM job_execution_logs
      WHERE created_at >= ?
      AND status = 'completed'
      ORDER BY duration_ms DESC
      LIMIT 10
    `, [since]);

    // Jobs falhados recentes
    const recentFailures = await db.query(`
      SELECT
        job_name,
        error_message,
        started_at,
        attempt,
        max_attempts
      FROM job_execution_logs
      WHERE created_at >= ?
      AND status = 'failed'
      ORDER BY started_at DESC
      LIMIT 20
    `, [since]);

    return {
      period,
      globalStats: globalStats[0],
      byJob: jobStats,
      slowestJobs,
      recentFailures,
    };
  }
}
```

---

## ✅ Checklist de Implementação (Expandido)

### Fase 1: Infraestrutura (2-3 dias)
- [ ] Instalar dependências
  - [ ] `npm install bull ioredis`
  - [ ] `npm install @bull-board/api @bull-board/fastify`
  - [ ] `npm install node-cron`
  - [ ] `npm install puppeteer` (crawler)
  - [ ] `npm install luxon` (datas/timezones)
- [ ] Configurar Redis
  - [ ] Criar conexão Redis
  - [ ] Configurar credenciais
  - [ ] Testar conectividade
- [ ] Criar QueueManager
  - [ ] Implementar singleton
  - [ ] Configurar filas com rate limiting
  - [ ] Adicionar logs
- [ ] Criar CronScheduler
  - [ ] Implementar agendamentos
  - [ ] Validar janelas de execução
  - [ ] Adicionar logs de disparo
- [ ] Setup BullBoard dashboard
  - [ ] Configurar rota `/admin/queues`
  - [ ] Adicionar autenticação
  - [ ] Personalizar UI
- [ ] Criar migration `job_execution_logs`
- [ ] Implementar `JobMetricsService`

### Fase 2: Jobs de Preços (3-4 dias)
- [ ] Implementar `MFinanceService`
  - [ ] Método `getQuote(segment, ticker)`
  - [ ] Mapeamento de categorias
  - [ ] Tratamento de erros
  - [ ] Rate limiting
- [ ] Implementar `BrapiService` (fallback)
  - [ ] Método `getQuote(ticker)`
  - [ ] Parsing de resposta
  - [ ] Tratamento de 404
- [ ] Implementar `UpdateMFinancePricesJob`
  - [ ] Lógica de seleção de tickers
  - [ ] Validação de janela de execução
  - [ ] Estratégia de fallback
  - [ ] Atualização de company metadata
  - [ ] Sincronização de closings
- [ ] Criar `PriceUpdateProcessor`
- [ ] Testar rate limiting (50 req/min)
- [ ] Testar fallback M_Finance → Brapi
- [ ] Validar atualização de `last_price_updated`

### Fase 3: Jobs de Sincronização (2-3 dias)
- [ ] Implementar `SyncBrapiStockListJob`
  - [ ] Paginação da API
  - [ ] Mapeamento de categorias/moedas
  - [ ] Criação/atualização de companies
  - [ ] Criação/atualização de tickers
  - [ ] Inativação de tickers ausentes
  - [ ] Geração de log detalhado
- [ ] Implementar `ReactivateTickersJob`
  - [ ] Seleção de tickers desativados
  - [ ] Cálculo de baseline
  - [ ] Reativação
- [ ] Criar `StockSyncProcessor`
- [ ] Testar sincronização completa
- [ ] Validar inativação de tickers

### Fase 4: Crawler de Dividendos (4-5 dias)
- [ ] Setup Puppeteer
  - [ ] Configurar headless mode
  - [ ] User agent rotation
  - [ ] Anti-bot measures
- [ ] Implementar `CrawlFiiDividendsJob`
  - [ ] Seleção de FIIs do consolidated
  - [ ] Validação de janela (30 dias)
  - [ ] Scraping com paginação
  - [ ] Parsing de valores/datas
  - [ ] Salvamento no banco
  - [ ] Atualização de `last_earnings_updated`
- [ ] Criar `DividendCrawlerProcessor`
- [ ] Testar rate limiting (1 FII a cada 3s)
- [ ] Testar timeout (30 minutos)
- [ ] Validar detecção de duplicados
- [ ] Testar em modo headless e headed

### Fase 5: Renovação de Assinaturas (3-4 dias)
- [ ] Implementar `GatewayService`
  - [ ] Integração com Asaas
  - [ ] Criação de cobranças
  - [ ] Tratamento de webhooks
- [ ] Implementar `RenewSubscriptionsJob`
  - [ ] Seleção de assinaturas
  - [ ] Cálculo de desconto
  - [ ] Criação de cobrança
  - [ ] Envio de email
- [ ] Integrar com RevenueCat (mobile)
- [ ] Criar `SubscriptionProcessor`
- [ ] Implementar emails de lembrete
- [ ] Testar dry-run mode
- [ ] Validar tratamento de erros

### Fase 6: Monitoramento (2-3 dias)
- [ ] Implementar logs estruturados
  - [ ] Logs por job
  - [ ] Contexto completo
  - [ ] Rotação de logs
- [ ] Implementar métricas
  - [ ] Taxa de sucesso/falha
  - [ ] Tempo médio de execução
  - [ ] Jobs mais lentos
- [ ] Configurar alertas
  - [ ] Email para falhas críticas
  - [ ] Webhook para Slack/Discord
  - [ ] Integração com Sentry
- [ ] Criar dashboard de métricas
  - [ ] Endpoint `/admin/jobs/metrics`
  - [ ] Gráficos de performance
  - [ ] Lista de falhas recentes
- [ ] Implementar health checks
  - [ ] Endpoint `/health/queues`
  - [ ] Status de Redis
  - [ ] Status de workers

### Fase 7: Testes e Migração (5-7 dias)
- [ ] Testes unitários
  - [ ] Cada job isoladamente
  - [ ] Mocks de APIs externas
  - [ ] Cobertura > 80%
- [ ] Testes de integração
  - [ ] Fluxo completo de cada job
  - [ ] Rate limiting
  - [ ] Retry e backoff
- [ ] Testes em staging
  - [ ] Executar jobs manualmente
  - [ ] Validar dados no banco
  - [ ] Monitorar performance
- [ ] Migração gradual
  - [ ] Desabilitar 1 comando Laravel por vez
  - [ ] Ativar job equivalente no Node.js
  - [ ] Monitorar por 48 horas
  - [ ] Validar consistência de dados
- [ ] Rollback plan
  - [ ] Documentar como reverter
  - [ ] Manter Laravel ativo em paralelo
  - [ ] Backup de dados antes da migração

---

## 🚨 Alertas e SLAs

### Alertas Configurados

```typescript
// services/monitoring/AlertService.ts
export class AlertService {
  // Alerta se job falha 3 vezes consecutivas
  static async notifyJobFailure(jobName: string, error: Error, attempts: number) {
    if (attempts >= 3) {
      await this.sendEmail({
        to: process.env.ADMIN_EMAIL!,
        subject: `[CRITICAL] Job Failed: ${jobName}`,
        body: `
          Job: ${jobName}
          Attempts: ${attempts}
          Error: ${error.message}
          Stack: ${error.stack}
          Time: ${new Date().toISOString()}
        `,
      });

      await this.sendSlackAlert({
        channel: '#alerts',
        text: `🚨 *Critical Job Failure*\n\`${jobName}\` failed after ${attempts} attempts\nError: ${error.message}`,
        color: 'danger',
      });
    }
  }

  // Alerta se muitos tickers desatualizados
  static async notifyStaleData(dataType: string, count: number, threshold: number) {
    if (count > threshold) {
      await this.sendEmail({
        to: process.env.ADMIN_EMAIL!,
        subject: `[WARNING] Stale Data: ${dataType}`,
        body: `${count} ${dataType} are outdated (threshold: ${threshold}). Check price update jobs.`,
      });
    }
  }

  // Alerta se renovação de assinatura falha
  static async notifySubscriptionRenewalFailed(subscriptionId: number, error: Error) {
    await this.sendEmail({
      to: process.env.ADMIN_EMAIL!,
      subject: `[URGENT] Subscription Renewal Failed`,
      body: `
        Subscription ID: ${subscriptionId}
        Error: ${error.message}
        Time: ${new Date().toISOString()}

        Action required: Check subscription and retry manually.
      `,
    });
  }

  // Alerta se crawler de dividendos tem muitas falhas
  static async notifyCrawlerHighFailureRate(failureRate: number, failedFiis: string[]) {
    if (failureRate > 0.2) {
      // Mais de 20% de falha
      await this.sendSlackAlert({
        channel: '#alerts',
        text: `⚠️ *High Failure Rate in FII Crawler*\nFailure rate: ${(failureRate * 100).toFixed(1)}%\nFailed FIIs: ${failedFiis.join(', ')}`,
        color: 'warning',
      });
    }
  }
}
```

### SLAs Definidos

| Job | Frequência | SLA | Alertar se |
|-----|-----------|-----|-----------|
| UpdateMFinancePrices | 1 minuto | 95% sucesso | < 90% em 1 hora |
| SyncBrapiStockList | Segunda 04:00 | 99% sucesso | Falha total |
| ReactivateTickers | 45 minutos | 99% sucesso | Falha total |
| CrawlFiiDividends | Diário 08:30 | 85% sucesso | < 75% |
| RenewSubscriptions | Diário 00:00 | 98% sucesso | < 95% |

---

## 💡 Observações Técnicas Críticas

### 1. Idempotência
Todos os jobs devem ser idempotentes:
- **UpdateMFinancePrices:** `UPSERT` em `ticker_prices` e `company_closings`
- **SyncBrapiStockList:** `firstOrCreate` em companies e tickers
- **CrawlFiiDividends:** Verifica existência antes de inserir dividendos
- **RenewSubscriptions:** Verifica se já existe cobrança pendente

### 2. Transações
Operações críticas devem usar transações:
- Renovação de assinaturas (cria cobrança + envia email)
- Sincronização de ativos (cria company + ticker)

### 3. Rate Limiting por Fonte
- **M_Finance:** 50 req/min
- **Brapi:** 100 req/min
- **Investidor10 (crawler):** 20 req/min (1 a cada 3s)
- **Asaas:** Sem limite documentado (usar 60 req/min)

### 4. Fallback Strategy
```
M_Finance (primário)
  ↓ (404 ou erro)
Brapi (fallback)
  ↓ (404 ou erro)
Alpha Vantage (último recurso - apenas USD)
  ↓ (falha total)
Marcar ticker como `can_update = 0`
```

### 5. Janelas de Execução
- **Preços:** Segunda-Sexta 08:00-18:00
- **Sync Brapi:** Segunda 04:00
- **Reativação:** 00min e 45min de cada hora
- **Crawler FII:** Diário 08:30
- **Renovações:** Diário 00:00

### 6. Último Dia Útil do Mês
Jobs de preços detectam automaticamente e salvam `company_closings` apenas no último dia útil do mês.

### 7. Smart Update (Dividendos)
Crawler de FII não processa tickers atualizados nas últimas 30 dias desde o último dividendo.

---

## 🔗 Referências Técnicas

- **Bull Documentation:** https://github.com/OptimalBits/bull
- **BullMQ (otimizado):** https://docs.bullmq.io/
- **BullBoard:** https://github.com/felixmosh/bull-board
- **node-cron:** https://github.com/node-cron/node-cron
- **Puppeteer:** https://pptr.dev/
- **Luxon (datas):** https://moment.github.io/luxon/
- **Brapi API:** https://brapi.dev/docs
- **M_Finance API:** (documentação interna)
- **Asaas API:** https://docs.asaas.com/

---

**Fim do Roadmap V8 (Versão Melhorada)**
