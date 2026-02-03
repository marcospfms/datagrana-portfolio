# Notas de Estudo (DB + Roadmaps + APIs Externas)

> **Contexto:** esta nota consolida o que foi analisado localmente nos arquivos do projeto (`datagrana-portfolio`).
> **Fontes locais:** migrations e roadmaps em `datagrana-portfolio/docs`.
> **Fontes externas:** documentação oficial das APIs Brapi, MFinance e B3.

---

## 1) Roadmaps implementados (status atual)

Baseado em `docs/roadmap-app.md`, `docs/roadmap-v3-companies.md`, `docs/roadmap-v4-consolidated.md`, `docs/roadmap-v5-portfolio.md`, `docs/roadmap-v6-crossing.md`:

- **V1 Auth**: login Google + email/senha, token Sanctum.
- **V2 Core**: bancos e contas.
- **V3 Companies**: categorias, empresas e tickers; busca de ativos no app.
- **V4 Consolidated**: posições reais + transações + summary.
- **V5 Portfolio**: carteiras ideais + composições + histórico.
- **V6 Crossing**: comparação ideal vs real + cálculos de compra/venda.
- **V7 Subscription Limits**: limites e regras de downgrade (backend).
- **V8 Scheduled Tasks**: migração dos jobs agendados do legado para Laravel.

Endpoints citados no `roadmap-app.md` (resumo):

- Auth: `POST /api/auth/login`, `POST /api/auth/google`, `GET /api/auth/me`, `GET /api/auth/profile`, `PATCH /api/auth/profile`, `PUT /api/auth/password`, `POST /api/auth/logout`, `POST /api/auth/logout-all`
- Banks/Accounts: `GET /api/banks`, `GET/POST/PUT/DELETE /api/accounts`
- Companies: `GET /api/companies/categories`, `GET /api/companies`, `GET /api/companies/popular`, `GET /api/companies/{companyTicker}`
- Consolidated: `GET /api/consolidated`, `GET /api/consolidated/{id}`, `POST /api/consolidated`, `PUT /api/consolidated/{id}`, `DELETE /api/consolidated/{id}`, `GET /api/consolidated/summary`
- Consolidated Transactions: `POST /api/consolidated/transactions`, `PUT /api/consolidated/transactions/{type}/{transactionId}`, `DELETE /api/consolidated/transactions/{type}/{transactionId}`
- Portfolios: `GET /api/portfolios`, `GET /api/portfolios/{id}`, `POST /api/portfolios`, `PUT /api/portfolios/{id}`, `DELETE /api/portfolios/{id}`
- Crossing: `GET /api/portfolios/{portfolio}/crossing`
- Compositions: `POST /api/portfolios/{portfolio}/compositions`, `PUT /api/compositions/batch`, `PUT /api/compositions/{composition}`, `DELETE /api/compositions/{composition}`
- Health: `GET /api/health`

---

## 2) Migrations e tabelas (por domínio)

### Core

- `users`
- `personal_access_tokens` (Sanctum)
- `banks`
- `accounts`
- `api_credentials`
- `coins`
- `calendar_closings`
- `user_settings`

Referências:
- `database/migrations/core/2025_01_01_000001_create_users_table.php`
- `database/migrations/core/2025_01_01_000004_create_personal_access_tokens_table.php`
- `database/migrations/core/2025_01_01_000005_create_banks_table.php`
- `database/migrations/core/2025_01_01_000006_create_accounts_table.php`
- `database/migrations/core/2025_01_01_000007_create_api_credentials_table.php`
- `database/migrations/core/2025_01_01_000008_create_coins_table.php`
- `database/migrations/core/2025_01_01_000009_create_calendar_closings_table.php`
- `database/migrations/core/2025_01_01_000010_create_user_settings_tables.php`

### Companies / Ativos de Renda Variável

- `company_category`
- `companies`
- `company_tickers`
- `company_closings`
- `company_splits`
- `company_indicators`
- `company_earnings`

Referências:
- `database/migrations/companies/2025_01_04_000001_create_company_category_table.php`
- `database/migrations/companies/2025_01_04_000002_create_companies_table.php`
- `database/migrations/companies/2025_01_04_000003_create_company_tickers_table.php`
- `database/migrations/companies/2025_01_04_000004_create_company_closings_table.php`
- `database/migrations/companies/2025_01_04_000006_create_company_splits_table.php`
- `database/migrations/companies/2025_01_04_000007_create_company_indicators_table.php`
- `database/migrations/companies/2025_01_07_000002_create_company_earnings_table.php`

Observações estruturais importantes:
- `company_tickers` guarda `last_price`, `last_price_updated`, `last_earnings_updated`.
- `company_closings` é histórico diário com `open/high/low/price/volume` e `previous_close`.
- `company_splits` registra desdobramentos (com campo `history_applied`).
- `company_indicators` tem chave composta (`key`, `year`, `company_ticker_id`).

### Consolidated (Posições reais + transações)

- `consolidated`
- `treasure_transaction`
- `company_transactions`
- `user_net_balance`
- `transaction_audit_logs`

Referências:
- `database/migrations/consolidated/2025_01_06_000001_create_consolidated_table.php`
- `database/migrations/consolidated/2025_01_06_000002_create_treasure_transaction_table.php`
- `database/migrations/consolidated/2025_01_06_000004_create_company_transactions_table.php`
- `database/migrations/consolidated/2025_01_06_000003_create_user_net_balance_table.php`
- `database/migrations/consolidated/2025_06_25_084615_create_transaction_audit_logs_table.php`

Observações estruturais importantes:
- `consolidated` relaciona `account_id` + `company_ticker_id` ou `treasure_id`.
- Campos de custo médio, quantidades e totais comprados/vendidos.

### Portfolio (Carteiras ideais)

- `portfolios`
- `portfolio_compositions`
- `portfolio_composition_histories`

Referências:
- `database/migrations/portfolio/2025_01_08_000001_create_portfolios_table.php`
- `database/migrations/portfolio/2025_01_08_000002_create_compositions_table.php`
- `database/migrations/portfolio/2025_01_08_000003_create_composition_histories_table.php`

Observações de regra (roadmap V5/V6):
- Composição aceita soma != 100%, app deve alertar.
- Histórico de remoções via `deleted_at`.
- Crossing calcula comparações ideal vs real e status `positioned / not_positioned / unwind_position`.

### Treasures (Tesouro Direto)

- `treasure_categories`
- `treasures`
- `treasure_closings`

Referências:
- `database/migrations/treasures/2025_01_05_000001_create_treasure_categories_table.php`
- `database/migrations/treasures/2025_01_05_000002_create_treasures_table.php`
- `database/migrations/treasures/2025_06_23_155526_create_treasure_closings_table.php`

### Earnings (Proventos)

- `earning_type`
- `earnings`

Referências:
- `database/migrations/earnings/2025_01_07_000001_create_earning_type_table.php`
- `database/migrations/earnings/2025_01_07_000003_create_earnings_table.php`

### Subscription / Gateway (fora do escopo do estudo principal)

- `subscriptions/*` e `subscription_limits/*`
- `gateway/*`

---

## 3) Fluxo do usuário (roadmap-app)

1. Login com Google ou email/senha.
2. Criar Account (corretora).
3. Registrar compras (consolidated).
4. Criar Portfolio com ativos e percentuais.
5. Visualizar Crossing (quanto comprar/ajustar).

---

## 4) Pontos de integração externa (observados no projeto)

Serviços externos documentados em `docs/roadmap-v8-scheduled-tasks-migration.md`:

| Serviço | Uso Principal | Prioridade |
|---------|---------------|------------|
| **MFinance** | Cotações em tempo real (ações, FIIs) | Primário |
| **Brapi** | Lista de ativos, cotações, históricos, fallback | Secundário |
| **AlphaVantage** | Fallback manual (não agendado) | Opcional |
| **Investidor10** | Scraping de dividendos de FIIs | Crawler |

Esses serviços alimentam:
- `company_tickers.last_price` e `last_price_updated`
- `company_closings` (histórico diário)
- `company_earnings` (proventos de FIIs)

---

## 5) APIs Externas - Documentação Técnica

### 5.1 Brapi API

**Fonte:** [brapi.dev/docs](https://brapi.dev/docs)

**Base URL:** `https://brapi.dev/api`

**Autenticação:**
- Query parameter: `?token=YOUR_TOKEN`
- Header: `Authorization: Bearer YOUR_TOKEN`
- Credenciais armazenadas em `api_credentials` com key `brapi_dev`

**Nota:** 4 tickers são gratuitos sem autenticação: PETR4, VALE3, MGLU3, ITUB4.

#### 5.1.1 Quote Endpoint (Cotação)

```
GET /api/quote/{tickers}
```

**Path Parameters:**

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `tickers` | string | Sim | Um ou mais tickers separados por vírgula (ex: `PETR4,VALE3`) |

**Query Parameters:**

| Parâmetro | Tipo | Valores | Descrição |
|-----------|------|---------|-----------|
| `token` | string | - | Token de autenticação (alternativa ao header) |
| `range` | string | 1d, 5d, 1mo, 3mo, 6mo, 1y, 2y, 5y, 10y, ytd, max | Período do histórico |
| `interval` | string | 1m, 2m, 5m, 15m, 30m, 60m, 90m, 1h, 1d, 5d, 1wk, 1mo, 3mo | Granularidade (requer range) |
| `fundamental` | boolean | true/false | Incluir P/L e LPA |
| `dividends` | boolean | true/false | Incluir histórico de dividendos |
| `modules` | array | Ver lista abaixo | Dados financeiros avançados |

**Módulos disponíveis:**

| Módulo | Descrição |
|--------|-----------|
| `summaryProfile` | Dados da empresa (endereço, setor, funcionários, site) |
| `balanceSheetHistory` | Balanço patrimonial anual |
| `balanceSheetHistoryQuarterly` | Balanço patrimonial trimestral |
| `defaultKeyStatistics` | Métricas TTM (P/L, ROE, Dividend Yield) |
| `incomeStatementHistory` | DRE anual |
| `incomeStatementHistoryQuarterly` | DRE trimestral |
| `financialData` | Dados financeiros TTM (receita, EBITDA, margens) |
| `cashflowHistory` | DFC anual |
| `cashflowHistoryQuarterly` | DFC trimestral |

**Response (200 OK):**

```json
{
  "results": [
    {
      "currency": "BRL",
      "marketCap": 416355902930,
      "symbol": "PETR4",
      "shortName": "PETROBRAS PN EDJ N2",
      "longName": "Petróleo Brasileiro S.A. - Petrobras",
      "regularMarketPrice": 31.1,
      "regularMarketChange": 0.17,
      "regularMarketChangePercent": 0.55,
      "regularMarketTime": "2025-08-29T20:07:36.000Z",
      "regularMarketDayHigh": 31.35,
      "regularMarketDayLow": 30.85,
      "regularMarketDayRange": "30.85 - 31.35",
      "regularMarketVolume": 27631700,
      "regularMarketPreviousClose": 30.93,
      "regularMarketOpen": 30.54,
      "fiftyTwoWeekHigh": 40.76,
      "fiftyTwoWeekLow": 28.86,
      "fiftyTwoWeekRange": "28.86 - 40.76",
      "logourl": "https://icons.brapi.dev/icons/PETR4.svg",
      "priceEarnings": 5.18,
      "earningsPerShare": 6.00,
      "usedInterval": "1d",
      "usedRange": "5d",
      "historicalDataPrice": [
        {
          "date": 1756126800,
          "open": 30.47,
          "high": 30.78,
          "low": 30.42,
          "close": 30.65,
          "volume": 21075300,
          "adjustedClose": 30.65
        }
      ],
      "dividendsData": {
        "cashDividends": [
          {
            "paymentDate": "2025-02-20T00:00:00.000Z",
            "rate": 0.66,
            "label": "JCP",
            "relatedTo": "1º Trimestre/2025"
          }
        ],
        "stockDividends": [
          {
            "factor": 2,
            "completeFactor": "2 para 1",
            "label": "DESDOBRAMENTO"
          }
        ]
      }
    }
  ],
  "requestedAt": "2025-08-30T15:53:07.499Z",
  "took": "0ms"
}
```

**Mapeamento para o banco:**

| Campo API | Campo DB |
|-----------|----------|
| `regularMarketPrice` | `company_tickers.last_price` |
| `regularMarketTime` | `company_tickers.last_price_updated` |
| `shortName` | `company_tickers.nickname` |
| `longName` | `companies.name` |
| `logourl` | `company_tickers.photo` |
| `historicalDataPrice[*]` | `company_closings` (open, high, low, close, volume) |
| `dividendsData.cashDividends[*]` | `company_earnings` |

#### 5.1.2 List Endpoint (Lista de Ativos)

```
GET /api/quote/list
```

**Query Parameters:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `search` | string | Filtro parcial por ticker |
| `sortBy` | string | Campo de ordenação: `name`, `close`, `change`, `change_abs`, `volume`, `market_cap_basic`, `sector` |
| `sortOrder` | string | Direção: `asc` ou `desc` (requer sortBy) |
| `limit` | integer | Resultados por página (mínimo: 1) |
| `page` | integer | Número da página (começa em 1) |
| `type` | string | Tipo de ativo: `stock`, `fund`, `bdr` |
| `sector` | string | Filtro por setor (ex: `Finance`, `Energy Minerals`) |

**Response (200 OK):**

```json
{
  "indexes": [
    { "stock": "^BVSP", "name": "IBOVESPA" }
  ],
  "stocks": [
    {
      "stock": "PETR4",
      "name": "PETROBRAS PN",
      "close": 36.71,
      "change": 3.26,
      "volume": 87666300,
      "market_cap": 497695817728,
      "logo": "https://icons.brapi.dev/icons/PETR4.svg",
      "sector": "Energy Minerals",
      "type": "stock"
    }
  ],
  "availableSectors": ["Energy Minerals", "Finance", "..."],
  "availableStockTypes": ["stock", "fund", "bdr"],
  "currentPage": 1,
  "totalPages": 5,
  "itemsPerPage": 10,
  "totalCount": 45,
  "hasNextPage": true
}
```

**Mapeamento type → category:**

| Brapi Type | DB Category |
|------------|-------------|
| `stock` | ACAO |
| `fund` | FII |
| `bdr` | BDR |
| `etf` | ETF |

#### 5.1.3 HTTP Status Codes

| Código | Condição |
|--------|----------|
| 200 | Sucesso |
| 400 | Parâmetro inválido (ex: range inválido) |
| 401 | Token inválido ou ausente |
| 402 | Limite do plano excedido |
| 404 | Ticker não encontrado |

**Rate Limits:** Controlados pelo plano. Plano gratuito permite 1 ticker por request.

**Fontes de dados:** B3 (cotações), CVM (demonstrações financeiras), BCB (indicadores econômicos).

---

### 5.2 MFinance API

**Fonte:** [mfinance.com.br/swagger](https://mfinance.com.br/swagger/index.html)

**Base URL:** `https://mfinance.com.br/api/v1`

**Versão:** 0.1.15

**Licença:** BSD 3-Clause

**Autenticação:**
- Header: `X-API-Key: YOUR_TOKEN`
- Credenciais armazenadas em `api_credentials` com key `m_finance`

#### 5.2.1 Stocks Endpoints

| Método | Endpoint | Parâmetros | Descrição |
|--------|----------|------------|-----------|
| GET | `/stocks` | `symbols` (query) | Lista de ações (filtro opcional) |
| GET | `/stocks/{symbol}` | `symbol` (path) | Cotação de uma ação |
| GET | `/stocks/symbols/` | - | Lista todos os símbolos disponíveis |
| GET | `/stocks/details/{symbol}` | `symbol` (path) | Detalhes da empresa |
| GET | `/stocks/dividends/{symbol}` | `symbol` (path) | Histórico de dividendos |
| GET | `/stocks/historicals/{symbol}` | `symbol`, `date`, `months` (1-180, default 3) | Histórico OHLC |
| GET | `/stocks/indicators` | `symbols` (query) | Indicadores de múltiplas ações |
| GET | `/stocks/indicators/{symbol}` | `symbol` (path) | Indicadores de uma ação |

#### 5.2.2 FIIs Endpoints

| Método | Endpoint | Parâmetros | Descrição |
|--------|----------|------------|-----------|
| GET | `/fiis` | `symbols` (query) | Lista de FIIs (filtro opcional) |
| GET | `/fiis/{symbol}` | `symbol` (path) | Cotação de um FII |
| GET | `/fiis/symbols/` | - | Lista todos os símbolos de FIIs |
| GET | `/fiis/dividends/{symbol}` | `symbol` (path) | Histórico de dividendos do FII |
| GET | `/fiis/historicals/{symbol}` | `symbol`, `date`, `months` (1-180, default 3) | Histórico OHLC do FII |

#### 5.2.3 Treasury Direct Endpoints

| Método | Endpoint | Parâmetros | Descrição |
|--------|----------|------------|-----------|
| GET | `/treasury-direct` | `symbols` (query) | Lista de títulos do Tesouro |
| GET | `/treasury-direct/{symbol}` | `symbol` (path) | Dados de um título específico |

#### 5.2.4 Exemplo de Request/Response

**Request:**
```bash
curl -X GET "https://mfinance.com.br/api/v1/stocks/PETR4" \
  -H "X-API-Key: YOUR_API_KEY"
```

**Response esperado (estrutura finance.Stock):**
```json
{
  "symbol": "PETR4",
  "name": "PETROBRAS PN",
  "lastPrice": 31.10,
  "price": 31.10,
  "close": 30.93,
  "change": 0.55,
  "volume": 27631700,
  "marketCap": 416355902930,
  "updatedAt": "2025-01-30T17:30:00Z"
}
```

**Mapeamento para o banco:**

| Campo API | Campo DB |
|-----------|----------|
| `lastPrice` ou `price` ou `close` | `company_tickers.last_price` |
| `updatedAt` | `company_tickers.last_price_updated` |
| `name` | `company_tickers.nickname` (se vazio) |

#### 5.2.5 Mapeamento de Segmentos

O serviço MFinanceTickerPriceUpdater usa este mapeamento para determinar qual endpoint chamar:

```php
$mFinanceSegments = [
    'ACAO' => 'stocks',
    'FII'  => 'fiis',
    'ETF'  => 'stocks',
];
```

**Nota:** BDRs não são suportados pelo MFinance, caindo para fallback Brapi.

---

### 5.3 B3 APIs (Área do Investidor)

**Fonte:** [developers.b3.com.br](https://developers.b3.com.br/apis/api-area-do-investidor)

**Manual Técnico:** [Download PDF (12/12/2024)](https://www.b3.com.br/lumis/portal/file/fileDownload.jsp?fileId=8AE490CA9358B1A70193BADF061C63AF)

> **Observação importante:** As APIs da B3 são exclusivamente B2B (business-to-business). Requerem contrato comercial e pacote de acesso. Não há acesso direto para desenvolvedores individuais.

#### 5.3.1 APIs Disponíveis

| API | Versão | Descrição |
|-----|--------|-----------|
| **Posição** | v3.3.1-rc2 | Saldo de investimentos nas contas (D-1) |
| **Negociação de Ativos** | v2.0.1-rc3 | Compras e vendas de ativos listados (D-1) |
| **Movimentação** | v2.0.2-rc8 | Transações nas contas do investidor (D-1) |
| **Eventos Provisionados** | v2.0.0-rc1 | Eventos corporativos de renda variável (D-1) |
| **Oferta Pública** | v2.0.0-rc1 | Participação em ofertas públicas |
| **Autorização Fintech** | v1.0.0-rc7 | Verifica autorização de dados do investidor |
| **API Guia** | v1.0.0-rc3 | Documentos de investidores autorizados |
| **Cadastro Investidor Fintechs** | v1.0.0-rc1 | Dados cadastrais do investidor |
| **FintechSistema** | v1.0.0-rc1 | Data da última carga do sistema |
| **Pacote de Acesso** | v1.0 | Gera credenciais para ambiente de certificação |
| **STVM Request Authorization** | v1.0.0-rc1 | Solicita token de custodiante |
| **STVM Response Authorization** | v1.0.0-rc1 | Recupera token de custodiante |

#### 5.3.2 Autenticação

**Métodos suportados:**
- **Client Credentials** (OAuth 2.0) - para aplicações server-to-server
- **ROPC (Resource Owner Password Credentials)** - fluxo com senha do usuário

**Ambientes:**
- Certificação (sandbox) - pacote de acesso gerado pela API
- Produção - pacote enviado pela B3 após contratação

#### 5.3.3 Características Técnicas

| Aspecto | Valor |
|---------|-------|
| Protocolo | REST |
| Formato | JSON |
| Atualização dos dados | D-1 (dia útil anterior) |
| Acesso | Exclusivo B2B (requer contrato) |

#### 5.3.4 Relevância para o Projeto

As APIs da B3 **não são utilizadas atualmente** no datagrana-portfolio devido à natureza B2B e requisitos de contratação. O projeto utiliza Brapi e MFinance como alternativas públicas.

**Possível uso futuro:**
- Importação automática de posições do investidor
- Sincronização de transações da corretora
- Eventos corporativos (dividendos, splits)

---

## 6) Estratégia de Fallback

O projeto implementa uma estratégia de fallback para garantir disponibilidade dos dados:

```
┌─────────────────────────────────────────────────────────────┐
│                    Fluxo de Cotações                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────┐     ┌──────────┐     ┌──────────────────┐    │
│  │ MFinance │ ──► │  Brapi   │ ──► │ AlphaVantage     │    │
│  │ (primário)│     │(fallback)│     │ (fallback manual)│    │
│  └──────────┘     └──────────┘     └──────────────────┘    │
│       │                │                    │               │
│       ▼                ▼                    ▼               │
│  ACAO, FII, ETF    ACAO, FII,         Qualquer ticker      │
│                    BDR, ETF           (rate limit 5/min)   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Condições de fallback MFinance → Brapi:**
- Ticker é BDR (não suportado pelo MFinance)
- HTTP 404 retornado
- Erro de conexão/timeout

**Condições de fallback Brapi → AlphaVantage:**
- Manual (não agendado)
- Usado quando ambos MFinance e Brapi falham

---

## 7) Tabela api_credentials

Configuração das credenciais na tabela `api_credentials`:

| key | url_base | auth_type | campos_usados |
|-----|----------|-----------|---------------|
| `brapi_dev` | `https://brapi.dev/api` | Bearer Token | `token`, `request_counter`, `request_limit` |
| `m_finance` | `https://mfinance.com.br/api/v1` | X-API-Key | `key`, `request_counter`, `request_limit` |
| `alpha_vantage` | `https://www.alphavantage.co/query` | Query param | `key` (apikey=) |

**Campos da tabela:**
- `name`: Nome descritivo
- `key`: Chave de identificação interna
- `token`: API key/token
- `url_base`: URL base da API
- `status`: 1=ativo, 0=inativo
- `request_counter`: Contador de requests (para rate limiting)
- `request_limit`: Limite de requests do plano
- `type_limit`: Tipo de limite (daily, monthly)
- `plan`: Nome do plano contratado

---

## 8) Referências Externas

### Documentação Oficial

- **Brapi:** https://brapi.dev/docs
- **MFinance Swagger:** https://mfinance.com.br/swagger/index.html
- **B3 Developers:** https://developers.b3.com.br/apis
- **B3 Manual Técnico (PDF):** https://www.b3.com.br/lumis/portal/file/fileDownload.jsp?fileId=8AE490CA9358B1A70193BADF061C63AF

### Alternativas não utilizadas

- **Dados de Mercado:** https://www.dadosdemercado.com.br/api/docs
- **HG Brasil Finance:** https://hgbrasil.com/finance
- **Fintz:** https://docs.fintz.com.br/endpoints/bolsa/

---

**Arquivo atualizado em:** 2025-02-03
**Última revisão:** Adição de documentação técnica das APIs Brapi e MFinance.
