# Roadmap V8 - Scheduled Tasks no datagrana-portfolio (Laravel)

**Status:** Planejamento
**Dependencias:** V1-V7 (completos)
**Objetivo:** Migrar 4 comandos agendados do `datagrana-web` (legado) para `datagrana-portfolio` (Laravel 12)

---

## Resumo

Ambos os projetos compartilham o mesmo banco de dados - **sem migrations novas**.
AlphaVantage fica como fallback opcional (nao agendado).

### Comandos a implementar

| # | Comando | Frequencia | Prioridade |
|---|---------|-----------|------------|
| 1 | `app:reactivate-tickers` | Cada 45min | Media |
| 2 | `app:update-mfinance-ticker-prices` | Cada 1min | Alta |
| 3 | `app:sync-brapi-stock-list` | Segunda 04:00 | Media |

> **Nota:** O comando `app:crawl-fii-dividends` foi descontinuado. O site Investidor10 mudou o layout, quebrando o crawler. Buscar APIs alternativas para dividendos de FIIs (ex: Brapi, StatusInvest API, ou CVM dados abertos).

---

## Fase 1 - Infraestrutura (Models + Helpers + Deps)

### 1.1 Dependencias

Nenhuma dependencia externa necessaria para os 3 comandos atuais (usam apenas HTTP client nativo do Laravel).

### 1.2 Criar 4 Models (tabelas ja existem no banco compartilhado)

| Arquivo | Tabela | Fonte legado |
|---------|--------|-------------|
| `app/Models/ApiCredential.php` | `api_credentials` | `datagrana-web/app/Models/Core/ApiCredential.php` |
| `app/Models/CompanyClosing.php` | `company_closings` | `datagrana-web/app/Models/Companies/CompanyClosing.php` |
| `app/Models/CompanyEarning.php` | `company_earnings` | `datagrana-web/app/Models/Companies/CompanyEarning.php` |
| `app/Models/EarningType.php` | `earning_type` | `datagrana-web/app/Models/Earnings/EarningType.php` |

> **Nota:** `CalendarClosing` nao e necessario - so era usado pelo AlphaVantage TickerPriceUpdater (opcional). Os services MFinance/Brapi usam `TradingHelper::isLastBusinessDayOfMonth()` (calculo puro com Carbon).

**Importante:** Namespace muda de `App\Models\Companies\*` / `App\Models\Core\*` / `App\Models\Earnings\*` para `App\Models\*` (flat), seguindo o padrao do portfolio. Ao implementar, copiar do legado e ajustar namespace + usar `protected function casts(): array` (Laravel 12).

#### ApiCredential

```php
namespace App\Models;

class ApiCredential extends Model
{
    protected $fillable = [
        'name', 'email', 'key', 'url_base', 'status',
        'request_counter', 'request_limit', 'type_limit', 'plan', 'token',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'request_counter' => 'integer',
            'request_limit' => 'integer',
        ];
    }
}
```

#### CompanyClosing

```php
namespace App\Models;

class CompanyClosing extends Model
{
    protected $table = 'company_closings';

    protected $fillable = [
        'company_ticker_id', 'date', 'open', 'high', 'low',
        'price', 'volume', 'previous_close', 'splitted',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'open' => 'decimal:8',
            'high' => 'decimal:8',
            'low' => 'decimal:8',
            'price' => 'decimal:8',
            'volume' => 'decimal:8',
            'previous_close' => 'decimal:8',
            'splitted' => 'integer',
        ];
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}
```

#### CompanyEarning

```php
namespace App\Models;

class CompanyEarning extends Model
{
    protected $table = 'company_earnings';

    protected $fillable = [
        'company_ticker_id', 'earning_type_id', 'origin',
        'status', 'value', 'approved_date', 'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'value' => 'decimal:8',
            'approved_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }

    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class);
    }
}
```

#### EarningType

```php
namespace App\Models;

class EarningType extends Model
{
    protected $table = 'earning_type';

    protected $fillable = [
        'name', 'short_name', 'label', 'key', 'icon', 'hex_color',
    ];

    public function companyEarnings(): HasMany
    {
        return $this->hasMany(CompanyEarning::class);
    }
}
```

### 1.3 Adicionar relationships no CompanyTicker existente

**Arquivo:** `app/Models/CompanyTicker.php`

Adicionar 2 relationships:

```php
public function closings(): HasMany
{
    return $this->hasMany(CompanyClosing::class);
}

public function companyEarnings(): HasMany
{
    return $this->hasMany(CompanyEarning::class);
}
```

### 1.4 Criar ApiResponse DTO

**Arquivo:** `app/Services/External/ApiResponse.php`
**Fonte:** `datagrana-web/app/Services/External/ApiResponse.php` (copiar, ajustar namespace)

```php
namespace App\Services\External;

use Illuminate\Http\Client\Response;

class ApiResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly ?string $error,
        public readonly int $statusCode,
        public readonly string $url,
        public readonly ?Response $response = null
    ) {}

    public static function success(mixed $data, int $statusCode = 200, string $url = ''): self { ... }
    public static function error(string $error, int $statusCode = 500, string $url = '', ?Response $response = null): self { ... }
    public function isSuccess(): bool { return $this->success; }
    public function hasError(): bool { return !$this->success; }
    public function getData(): mixed { return $this->data; }
    public function getError(): ?string { return $this->error; }
    public function getStatusCode(): int { return $this->statusCode; }
    public function getUrl(): string { return $this->url; }
    public function getResponse(): ?Response { return $this->response; }
    public function toArray(): array { ... }
}
```

### 1.5 Criar TradingHelper

**Arquivo:** `app/Helpers/TradingHelper.php`

Extrair logica duplicada dos commands e services legado:

```php
namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class TradingHelper
{
    /**
     * Mapa de referencia de categoria para segmento MFinance.
     * Legado: Lists::$type_m_finance
     */
    public static array $mFinanceSegments = [
        'ACAO' => 'stocks',
        'FII'  => 'fiis',
        'ETF'  => 'stocks',
    ];

    /**
     * Verifica se estamos na janela de pregao (seg-sex 08:00-18:59).
     * Legado: UpdateTickerPricesCommand::canExecute()
     */
    public static function isTradingWindow(bool $forced = false): bool
    {
        if ($forced) {
            return true;
        }
        $now = Carbon::now();
        $dayOfWeek = (int) $now->dayOfWeekIso; // 1=seg ... 7=dom
        $hour = (int) $now->format('H');
        return $dayOfWeek >= 1 && $dayOfWeek <= 5 && $hour > 7 && $hour < 19;
    }

    /**
     * Verifica se a data e o ultimo dia util do mes.
     * Proximo dia util esta em mes diferente.
     * Legado: MFinanceTickerPriceUpdater::isLastBusinessDayOfMonth()
     */
    public static function isLastBusinessDayOfMonth(Carbon $date): bool
    {
        $nextBusinessDay = $date->copy()->addDay();
        while ($nextBusinessDay->isWeekend()) {
            $nextBusinessDay->addDay();
        }
        return $nextBusinessDay->month !== $date->month;
    }

    /**
     * Extrai valor decimal com 8 casas de um payload usando lista de chaves.
     * Legado: MFinanceTickerPriceUpdater::extractDecimal()
     */
    public static function extractDecimal(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_numeric($value)) {
                return number_format((float) $value, 8, '.', '');
            }
        }
        return null;
    }
}
```

### 1.6 Config e Docker

#### docker/supervisord.conf

Adicionar programa `schedule-worker` (mesmo padrao do `queue-worker` existente):

```ini
[program:schedule-worker]
command=php /var/www/html/artisan schedule:work
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3
```

---

## Fase 2 - Services de API Externa

### 2.1 BrapiService

**Arquivo:** `app/Services/External/Brapi/BrapiService.php`
**Fonte:** `datagrana-web/app/Services/External/Brapi/BrapiService.php` (572 linhas)

- API: `https://brapi.dev/api`
- Auth: Bearer token da tabela `api_credentials` (key: `brapi_dev`)
- Rate limit tracking via `api_credentials.request_counter` / `request_limit`
- Free plan validation: 1 ticker por request, modulos/ranges limitados

Metodos necessarios para os commands:
- `getQuote(string|array $tickers, ...)` - cotacao de 1+ tickers
- `listStocks(...)` - lista paginada de ativos (usado pelo sync)

Metodos adicionais (manter paridade com legado):
- `getCrypto()`, `getCurrency()`, `getInflation()`, `getDividends()` (todos guardados por `guardPaidFeature()`)
- `checkRateLimit()`, `incrementRequestCounter()`, `resetRequestCounter()`
- `isPaidPlan()`, `validateQuoteRequestForFreePlan()` - validacoes de plano gratuito

### 2.2 Brapi TickerPriceUpdater

**Arquivo:** `app/Services/External/Brapi/TickerPriceUpdater.php`
**Fonte:** `datagrana-web/app/Services/External/Brapi/TickerPriceUpdater.php` (274 linhas)

- `getEligibleTickers(int $limit, bool $onlyActive, int $staleMinutes)` - query complexa:
  - `status=1, can_update=1`
  - company ativa com category em [ACAO, FII, ETF, BDR] e coin BRL/USD
  - stale check: `last_price_updated IS NULL OR <= now - staleMinutes`
  - se closing day: ignora stale check, inclui todos os consolidated
  - se onlyActive + nao closing: consolidated com `quantity_current > 0` e `closed = false/null`
  - order: `COALESCE(last_price_updated, '1970-01-01') ASC`
- `updateTickers(Collection $tickers)` - chunk size 1 (free plan), chama `persistTickerData()`
  - Resposta Brapi: `results[].symbol` para mapear por ticker
- `persistTickerData()` - usa `regularMarketPrice` para price
  - Atualiza `last_price`, `last_price_updated`
  - `updateCompanyMeta()` - preenche `name` (longName), `nickname` (shortName), `photo` (logourl) se vazios
  - `syncClosing()` - usa `regularMarketTime` para data, cria/atualiza `company_closings` via `updateOrCreate`
    - Campos: `regularMarketOpen`, `regularMarketDayHigh`, `regularMarketDayLow`, `regularMarketPreviousClose`, `regularMarketVolume`

### 2.3 Brapi StockListSynchronizer

**Arquivo:** `app/Services/External/Brapi/StockListSynchronizer.php`
**Fonte:** `datagrana-web/app/Services/External/Brapi/StockListSynchronizer.php` (468 linhas)

- `sync(int $limit, int $maxPages)` - paginacao via `brapiService->listStocks()`, DB::transaction
- `normalizeCode(string $code)` - remove sufixo `F` (ex: `PETR4F` -> `PETR4`)
- `resolveCompany(CompanyCategory, stock, code)` - busca por CNPJ/nome/nickname, firstOrCreate
- `updateCompany()` - atualiza category, name, nickname, photo, status se necessario
- `deactivateMissingTickers(array $activeCodes)` - `status=0, can_update=0` para tickers nao retornados
- `updateCompanyStatuses()` - sincroniza `companies.status` baseado em tickers ativos
- `mapTypeToReference()` - mapa:
  ```
  stock/common_stock/preferred_stock -> ACAO
  fund/reit/fii/fii_brazil -> FII
  etf -> ETF
  bdr -> BDR
  ```
- `inferReferenceByCurrency()` - fallback por currency/exchange (BRL/BVMF/B3 -> ACAO)
- `getLastLogDetails()` - array de strings para log detalhado

### 2.4 MFinanceService

**Arquivo:** `app/Services/External/MFinance/MFinanceService.php`
**Fonte:** `datagrana-web/app/Services/External/MFinance/MFinanceService.php` (179 linhas)

- API: `https://mfinance.com.br/api`
- Auth: `X-API-Key` header da tabela `api_credentials` (key: `m_finance`)
- Rate limit: via `request_counter` / `request_limit` / `type_limit`
- `makeRequest()` - wrapper com rate limit check, increment, error handling
- `getQuote(string $segment, string $ticker)` - `GET /{segment}/{ticker}`
- `getHistorical(string $segment, string $ticker, int $months)` - `GET /{segment}/historicals/{ticker}`
- `getRequestCounter()`, `getRequestLimit()`, `getLimitType()`, `resetRequestCounter()`

### 2.5 MFinance TickerPriceUpdater

**Arquivo:** `app/Services/External/MFinance/MFinanceTickerPriceUpdater.php`
**Fonte:** `datagrana-web/app/Services/External/MFinance/MFinanceTickerPriceUpdater.php` (306 linhas)

- Construtor injeta `MFinanceService` + `BrapiTickerPriceUpdater` (fallback)
- `getEligibleTickers()` - mesma query complexa do Brapi updater (compartilhada)
- `updateTickers(Collection $tickers)` - itera cada ticker:
  - BDR -> `handleFallback()` direto (MFinance nao suporta)
  - `resolveSegment()` via `TradingHelper::$mFinanceSegments` (ACAO->stocks, FII->fiis, ETF->stocks)
  - Segment null -> `handleFallback()`
  - Response 404 -> `handleFallback()`
  - Response nao-sucesso -> marca como failed
  - Payload invalido -> marca como failed
  - Sucesso -> `persistTickerData()`
- `handleFallback(CompanyTicker, &$summary)` - delega para `brapiTickerPriceUpdater->updateTickers(collect([$ticker]))`
- `persistTickerData()` - extrai preco de `[lastPrice, price, close]`
  - Preco null/zero -> `can_update=0` (desativa)
  - Atualiza `last_price`, `last_price_updated`
  - `updateCompanyMeta()` - preenche `name` (companyName), `nickname` (shortName), `photo` (logo) se vazios
  - `syncClosing()` - extrai campos: `[priceOpen/open]`, `[high/dayHigh/max]`, `[low/dayLow/min]`, `[closingPrice/previousClose]`, `[volume]`
    - Data de `updatedAt` ou `latestTradingDay`, fallback para `now()`
    - So cria closing se `isLastBusinessDayOfMonth()` (via `TradingHelper`)
    - `CompanyClosing::updateOrCreate` por `[company_ticker_id, date]`

### 2.6 AlphaVantage (opcional - nao agendado)

**Arquivos:**
- `app/Services/External/AlphaVantage/AlphaVantageService.php`
- `app/Services/External/AlphaVantage/TickerPriceUpdater.php`

**Fonte:** `datagrana-web/app/Services/External/AlphaVantage/`

- API: `https://www.alphavantage.co/query` (ou RapidAPI)
- Auth: `apikey` param da tabela `api_credentials` (key: `alpha_vantage`)
- Metodos: `getGlobalQuote(ticker)` - adiciona sufixo `.SA` para B3, `getMonthlySeries(ticker)`
- TickerPriceUpdater: mesma estrutura dos outros updaters, fallback para Brapi
- **Implementar por ultimo** - fallback manual, nao entra no scheduler

---

## Fase 3 - Artisan Commands

### 3.1 ReactivateTickersCommand (mais simples, implementar primeiro)

**Arquivo:** `app/Console/Commands/Tickers/ReactivateTickersCommand.php`
**Fonte:** `datagrana-web/app/Console/Commands/Tickers/ReactivateTickersCommand.php` (66 linhas)

```php
protected $signature = 'app:reactivate-tickers
    {--limit=50 : Numero maximo de tickers reativados por execucao}
    {--cooldown=45 : Tempo minimo (min) desde a ultima tentativa antes da reativacao}
    {--stale-minutes=120 : Intervalo usado na fila principal para considerar tickers vencidos}';

protected $description = 'Reativa tickers desativados apos cooldown, recolocando-os no final da fila de atualizacao';
```

**Logica:**
1. Query: `can_update=0, status=1, (updated_at IS NULL OR updated_at <= now - cooldown)`
2. Order: `COALESCE(last_price_updated, updated_at, created_at) ASC`
3. Calcula `adjustment = max(staleMinutes - cooldown, 0)`, `baseline = now() - adjustment`
4. Para cada ticker: `can_update=1, last_price_updated=baseline` (coloca no fim da fila)
5. Log com contagem e codigos reativados

**Sem dependencia de service externo** - ideal para testar primeiro.

### 3.2 UpdateMFinanceTickerPricesCommand (mais critico)

**Arquivo:** `app/Console/Commands/MFinance/UpdateTickerPricesCommand.php`
**Fonte:** `datagrana-web/app/Console/Commands/MFinance/UpdateTickerPricesCommand.php` (108 linhas)

```php
protected $signature = 'app:update-mfinance-ticker-prices
    {--limit=50 : Numero maximo de tickers a processar}
    {--only-active : Considera apenas tickers com posicao consolidada}
    {--stale-minutes=120 : Intervalo minimo em minutos desde a ultima atualizacao}
    {--force : Forca execucao fora da janela de horario padrao}';

protected $description = 'Atualiza cotacoes e fechamentos dos tickers utilizando a API m_finance';
```

**Logica:**
1. Verifica `TradingHelper::isTradingWindow($force)` - seg-sex 08:00-18:59
2. Injeta `MFinanceTickerPriceUpdater` via construtor
3. `$tickers = $priceUpdater->getEligibleTickers($limit, $onlyActive, $staleMinutes)`
4. `$summary = $priceUpdater->updateTickers($tickers)`
5. Output: tabela `[Total, Sucesso, Desativados, Falhas]` + detalhes agrupados por status
6. Try/catch com `Log::error` para falhas

### 3.3 SyncStockListCommand

**Arquivo:** `app/Console/Commands/Brapi/SyncStockListCommand.php`
**Fonte:** `datagrana-web/app/Console/Commands/Brapi/SyncStockListCommand.php` (76 linhas)

```php
protected $signature = 'app:sync-brapi-stock-list
    {--limit=100 : Quantidade de ativos por pagina}
    {--pages=10 : Numero maximo de paginas a percorrer}';

protected $description = 'Sincroniza a lista de acoes/fiis/etfs da Brapi com companies e company_tickers locais';
```

**Logica:**
1. Injeta `StockListSynchronizer` via construtor
2. `$summary = $synchronizer->sync($limit, $pages)`
3. Tabela: `[Processados, Criados, Atualizados, Inativados, Empresas criadas, Empresas atualizadas]`
4. Log detalhado em `storage/logs/sync-brapi-stock-list_{timestamp}.log`
5. Log de erros em `storage/logs/sync-brapi-stock-list_errors.log`

---

## Fase 4 - Scheduler

**Arquivo:** `routes/console.php`

Substituir o `inspire` command padrao por:

```php
use Illuminate\Support\Facades\Schedule;

// Comandos agendados só rodam em produção
// Em sandbox, o scheduler roda mas não executa nada
if (app()->environment('production')) {
    // MFinance - cada minuto (auto-regula para horario de pregao via TradingHelper)
    Schedule::command('app:update-mfinance-ticker-prices --only-active --stale-minutes=30 --limit=50')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    // Reativacao - cada 45 minutos (0min e 45min de cada hora)
    Schedule::command('app:reactivate-tickers --limit=100 --cooldown=45 --stale-minutes=120')
        ->cron('0,45 * * * *')
        ->withoutOverlapping()
        ->runInBackground();

    // Sync lista de ativos - segunda 04:00
    Schedule::command('app:sync-brapi-stock-list --limit=100 --pages=999')
        ->cron('0 4 * * 1')
        ->withoutOverlapping();

}
```

> **Nota:** O wrapper `if (app()->environment('production'))` garante que os comandos só executam em produção. Em ambiente sandbox, o `schedule:work` roda mas não dispara nenhum comando.

---

## Fase 5 - Docker/Producao

### 5.1 supervisord.conf

Adicionar `schedule-worker` (ja detalhado em 1.6):

```ini
[program:schedule-worker]
command=php /var/www/html/artisan schedule:work
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
startretries=3
```

> **Status:** Ja implementado e commitado.

---

## Fase 6 - Verificacao

### Testes manuais

```bash
# 1. Reativacao (sem deps externas)
php artisan app:reactivate-tickers --limit=5

# 2. Precos MFinance (com --force para ignorar horario)
php artisan app:update-mfinance-ticker-prices --only-active --limit=3 --force

# 3. Sync Brapi (1 pagina)
php artisan app:sync-brapi-stock-list --limit=10 --pages=1

# 4. Listar schedule
php artisan schedule:list
```

### Validacao no banco

- `company_tickers.last_price` e `last_price_updated` atualizados
- `company_tickers.can_update` reativado
- `company_closings` criados no ultimo dia util
- `api_credentials.request_counter` incrementando

---

## Inventario de Arquivos

### Criar (14 arquivos)

| # | Arquivo |
|---|---------|
| 1 | `app/Models/ApiCredential.php` |
| 2 | `app/Models/CompanyClosing.php` |
| 3 | `app/Models/CompanyEarning.php` |
| 4 | `app/Models/EarningType.php` |
| 5 | `app/Helpers/TradingHelper.php` |
| 6 | `app/Services/External/ApiResponse.php` |
| 7 | `app/Services/External/Brapi/BrapiService.php` |
| 8 | `app/Services/External/Brapi/TickerPriceUpdater.php` |
| 9 | `app/Services/External/Brapi/StockListSynchronizer.php` |
| 10 | `app/Services/External/MFinance/MFinanceService.php` |
| 11 | `app/Services/External/MFinance/MFinanceTickerPriceUpdater.php` |
| 12 | `app/Services/External/AlphaVantage/AlphaVantageService.php` (opcional) |
| 13 | `app/Services/External/AlphaVantage/TickerPriceUpdater.php` (opcional) |
| 14 | `app/Console/Commands/Tickers/ReactivateTickersCommand.php` |
| 15 | `app/Console/Commands/MFinance/UpdateTickerPricesCommand.php` |
| 16 | `app/Console/Commands/Brapi/SyncStockListCommand.php` |

### Modificar (2 arquivos)

| # | Arquivo | Alteracao |
|---|---------|-----------|
| 1 | `app/Models/CompanyTicker.php` | +2 relationships (`closings`, `companyEarnings`) |
| 2 | `routes/console.php` | Substituir inspire por 3 schedules (ja feito) |

---

## Ordem de implementacao

| Passo | Descricao | Dependencia | Status |
|-------|-----------|-------------|--------|
| 1 | Models (4 novos + editar CompanyTicker) | - | Pendente |
| 2 | ApiResponse DTO + TradingHelper | - | Pendente |
| 3 | BrapiService + Brapi TickerPriceUpdater | Models, ApiResponse | Pendente |
| 4 | ReactivateTickersCommand (testar) | Models | Pendente |
| 5 | MFinanceService + MFinance TickerPriceUpdater | Models, ApiResponse, TradingHelper, BrapiTPU | Pendente |
| 6 | UpdateMFinanceTickerPricesCommand (testar) | MFinanceTPU, TradingHelper | Pendente |
| 7 | StockListSynchronizer + SyncStockListCommand (testar) | BrapiService, Models | Pendente |
| 8 | AlphaVantage services (opcional) | Models, ApiResponse, BrapiTPU | Opcional |
| 9 | routes/console.php (scheduler) | Todos os commands | **Feito** |
| 10 | supervisord.conf (schedule-worker) | - | **Feito** |

---

## Pontos de Falha, Riscos e Melhorias de Arquitetura

### Possiveis pontos de falha

| Ponto | Risco | Mitigacao |
|-------|-------|-----------|
| **Rate limit das APIs externas** | Brapi/MFinance bloqueiam requests, `request_counter` estoura | `checkRateLimit()` ja implementado nos services. Respeitar `request_limit` da tabela `api_credentials`. |
| **Banco compartilhado - dupla execucao** | Scheduler ativo no portfolio E no legado ao mesmo tempo = dados duplicados, race conditions | **Desativar no legado ANTES** de ativar no portfolio. Documentar procedimento. |
| **schedule:work morre no container** | Supervisor nao reinicia `schedule-worker` corretamente | Configurar `autorestart=true` com `startretries=3` no supervisord (ja feito). |
| **MFinance fora do ar** | Fallback para Brapi, mas Brapi tem plano free (1 req/vez) = lento | Ja implementado: `handleFallback()` delega para BrapiTickerPriceUpdater. Log de warning quando fallback e acionado. |
| **Preco zerado/null desativa ticker permanentemente** | `can_update=0` sem mecanismo de recuperacao | `ReactivateTickersCommand` (cada 45min) reativa tickers apos cooldown. Ciclo: desativa -> cooldown -> reativa -> tenta novamente. |
| **Ambiente sandbox executa scheduler** | Commands rodam em sandbox consumindo API desnecessariamente | Wrapper `if (app()->environment('production'))` em `routes/console.php` (ja feito). |

### Melhorias de arquitetura (futuro, nao bloqueia V8)

| Melhoria | Descricao | Beneficio |
|----------|-----------|-----------|
| **Extrair query `getEligibleTickers()` para trait/scope** | MFinanceTPU e BrapiTPU tem a mesma query complexa duplicada | DRY, facilita manutencao. Possivel `scopeEligibleForPriceUpdate()` no CompanyTicker. |
| **Services como singletons registrados no container** | BrapiService e MFinanceService carregam credenciais no construtor (query no banco) - registrar como singleton evita queries repetidas | Performance: 1 query por request cycle ao inves de N. |
| **Aderencia ao padrao de Services (docs/patterns/services.md)** | O padrao do portfolio define que Services devem: usar DI no construtor, retornar tipos explicitos, usar exceptions customizadas, usar DB::transaction para operacoes criticas | Os services legado ja seguem DI e transactions. Adicionar return types explicitos e considerar exceptions customizadas (ex: `ApiRateLimitException`, `TickerPriceUnavailableException`). |
| **Logging estruturado** | Commands usam `Log::info/warning/error` sem contexto padronizado | Definir formato padrao de contexto: `['command' => ..., 'ticker' => ..., 'source' => ..., 'duration_ms' => ...]`. |
| **Metricas de execucao** | Nao ha tracking de tempo de execucao nem taxa de sucesso por command | Possibilidade futura: tabela `job_execution_logs` ou integracao com monitoring externo. |
| **API de dividendos FII** | Buscar API alternativa para dividendos de FIIs (crawler descontinuado) | Possibilidades: Brapi (pago), StatusInvest API, CVM dados abertos, ou B3 dados publicos. |

### Aderencia aos padroes do portfolio (docs/patterns/)

| Padrao | Status | Observacao |
|--------|--------|------------|
| **Services em `app/Services/External/`** | OK | Segue a estrutura definida em `patterns/services.md` (`External/` para APIs externas). |
| **Nomenclatura PascalCase + sufixo** | OK | `BrapiService`, `MFinanceService`, `StockListSynchronizer`, `TickerPriceUpdater`. |
| **DI no construtor** | OK | Todos os services recebem dependencias via construtor (ex: `MFinanceTPU` recebe `MFinanceService` + `BrapiTPU`). |
| **Models flat em `App\Models\*`** | OK | Diferente do legado (`Core\*`, `Companies\*`, `Earnings\*`), segue padrao flat do portfolio. |
| **Commands nao sao Controllers** | N/A | Commands nao passam por Controllers/Resources/FormRequests - padrao diferente. A logica de negocio fica nos Services, nao nos Commands (Command = thin, Service = fat). |
| **`protected function casts(): array`** | OK | Laravel 12 method syntax, nao `$casts` property. Portfolio ja usa esse padrao. |

---

## Cuidados

- **Namespace flat:** `App\Models\*` (nao usar subpastas como no legado `Core\*`, `Companies\*`, `Earnings\*`)
- **Boolean casts:** CompanyTicker casta `can_update` como boolean - queries com `where('can_update', 0)` funcionam, mas comparacoes devem usar `!$ticker->can_update`
- **CarbonImmutable:** Portfolio usa CarbonImmutable - `now()->subMinutes()` retorna nova instancia (ok, nao muta)
- **Banco compartilhado:** Ao ativar scheduler no portfolio, **desativar no legado** para evitar duplicidade
- **Rate limits:** Brapi free plan (1 ticker/request via chunk size 1), MFinance (tracked na tabela `api_credentials`)
- **Relationships legado:** No legado a FK eh `company_ticker_id` (verificar se CompanyClosing e CompanyEarning usam `company_ticker_id` e nao `ticker_id`)
- **EarningType table:** Nome da tabela eh `earning_type` (singular, nao `earning_types`)
- **Company relationships:** O CompanyTicker no portfolio ja tem `company()` com eager loading de `companyCategory` - os services precisam de `company.companyCategory.coin` para queries
