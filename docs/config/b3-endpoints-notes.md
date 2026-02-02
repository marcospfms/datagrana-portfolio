# Notas de Estudo (DB + Roadmaps)

> **Contexto:** esta nota consolida o que foi analisado localmente nos arquivos do projeto (`datagrana-portfolio`).
> **Fontes locais:** migrations e roadmaps em `datagrana-portfolio/docs`.

## 1) Roadmaps implementados (status atual)

Baseado em `docs/roadmap-app.md`, `docs/roadmap-v3-companies.md`, `docs/roadmap-v4-consolidated.md`, `docs/roadmap-v5-portfolio.md`, `docs/roadmap-v6-crossing.md`:

- **V1 Auth**: login Google + email/senha, token Sanctum.
- **V2 Core**: bancos e contas.
- **V3 Companies**: categorias, empresas e tickers; busca de ativos no app.
- **V4 Consolidated**: posições reais + transações + summary.
- **V5 Portfolio**: carteiras ideais + composições + histórico.
- **V6 Crossing**: comparação ideal vs real + cálculos de compra/venda.
- **V7 Subscription Limits**: limites e regras de downgrade (backend).

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

## 3) Fluxo do usuário (roadmap-app)

1. Login com Google ou email/senha.
2. Criar Account (corretora).
3. Registrar compras (consolidated).
4. Criar Portfolio com ativos e percentuais.
5. Visualizar Crossing (quanto comprar/ajustar).

## 4) Pontos de integração externa (observados no projeto)

- Existem serviços externos documentados em `docs/roadmap-v8-scheduled-tasks-migration.md` para:
  - **Brapi** (lista de ativos, cotações e históricos).
  - **MFinance** (cotações/fechamentos).
  - **AlphaVantage** (fallback).

Esses serviços alimentam:
- `company_tickers.last_price` e `last_price_updated`
- `company_closings` (histórico diário)

## 5) Próximas validações (não estudadas aqui)

- Endpoints específicos de APIs externas (ex.: B3) exigem documentação oficial e acesso.
- Qualquer mapeamento de endpoints externos deve ser feito com base em Swagger/contratos oficiais.

---

**Arquivo gerado automaticamente para organização interna.**

## 6) B3 – Endpoints sugeridos e exemplos JSON (pendente)

> **Observação importante:** o portal público da B3 lista as APIs, mas os **exemplos de JSON** (request/response) ficam no **Swagger** de cada API. Esse Swagger normalmente exige acesso B2B/pacote habilitado. Sem esse acesso, os exemplos não ficam disponíveis para consulta.

### 6.1 Área do Investidor (mais aderente ao escopo)

APIs de interesse:
- **Posição** (posição por investidor – D-1)
- **Negociação de Ativos** (compra/venda – D-1)
- **Movimentação** (movimentações nas contas – D-1)
- **Eventos Provisionados** (eventos corporativos – D-1)
- **Oferta Pública**
- **Autorização Fintech / STVM** (consentimento)

**Exemplos JSON:** _pendente (depende do Swagger da B3)._

### 6.2 Tesouro Direto

APIs de interesse:
- **Bonds** (informações de títulos)
- **Positions** (posição em títulos)
- **Orders** (ordens)
- **Custom Data**

**Exemplos JSON:** _pendente (depende do Swagger da B3)._

### 6.3 Autenticação

APIs de interesse:
- **Client Credentials**
- **ROPC** (Password)

**Exemplos JSON:** _pendente (depende do Swagger da B3)._

---

## 7) Como obter os exemplos JSON oficiais (quando houver acesso)

1. Acesse o portal da B3 e entre na API desejada.
2. Use o link **Download** do Swagger (JSON/YAML).
3. Extraia exemplos de `requestBody` e `responses` do OpenAPI.

**Saída esperada para esta nota:** adicionar exemplos reais em JSON para cada endpoint sugerido.
