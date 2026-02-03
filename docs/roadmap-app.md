# Roadmap - DataGrana Portfolio API

> Documentacao completa para implementacao da API do modulo de Carteiras de Investimento usando Laravel + Sanctum para consumo em React Native.

---

## Status atual (Backend + App)

- ✅ V1 Auth, V2 Core, V3 Companies (search), V4 Consolidated, V5 Portfolio e V6 Crossing implementados no backend.
- ✅ V7 Subscription Limits implementado no backend (limites + regras de downgrade por `created_at`).
- ✅ Regra: limites e bloqueios (ex.: `is_locked`) calculados no backend; frontend consome pronto.
- ✅ App consumindo: auth, accounts, consolidated (lista, resumo, transacoes), portfolios/compositions, crossing.
- 🔜 Evolucao pos-MVP (app): companies categories/popular/detail, update batch de composicoes, historico detalhado de composicoes.
- 🔜 Fluxos de assinatura (app + backend) definidos em V7: downgrade permitido (efeito na renovação), aviso explícito no app; cancelamento rígido só em trial.
- Arquivos de referencia (app):
  - `datagrana-app/app/(tabs)/(home)/index.tsx`
  - `datagrana-app/app/(tabs)/(assets)/transactions/*`
  - `datagrana-app/app/(tabs)/(portfolios)/*`

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Arquitetura do Sistema](#2-arquitetura-do-sistema)
3. [Estrutura de Banco de Dados](#3-estrutura-de-banco-de-dados)
4. [Sistema de Autenticacao](#4-sistema-de-autenticacao)
5. [Models e Relacionamentos](#5-models-e-relacionamentos)
6. [Controllers e Endpoints API](#6-controllers-e-endpoints-api)
7. [Services e Helpers](#7-services-e-helpers)
8. [Regras de Negocio](#8-regras-de-negocio)
9. [Fases de Implementacao](#9-fases-de-implementacao)
10. [Dependencias e Pacotes](#10-dependencias-e-pacotes)

---

## 1. Visao Geral

### 1.1 O que e o modulo Portfolio

O **Portfolio** e um sistema de gestao de carteiras de investimento em **Renda Variavel** (Acoes, FIIs, ETFs) que permite aos usuarios:

- Criar multiplas carteiras com metas financeiras
- Definir alocacao percentual ideal de ativos
- Comparar a composicao ideal com a posicao real consolidada
- Rastrear historico de modificacoes na carteira
- Calcular quanto comprar de cada ativo para atingir a meta

### 1.2 Fluxo do Usuario

1. Login com Google ou email/senha (cria usuario se nao existe no Google)
2. Criar uma Account (conta na corretora)
3. Cadastrar compras de ativos (posicoes consolidadas)
4. Criar Portfolio com ativos e percentuais
5. Visualizar comparacao (Crossing)

### 1.3 Fluxos de assinatura no app (Resumo)

- **Planos disponíveis:** permite **upgrade** e **downgrade**.
- **Downgrade:** exibir aviso de que a mudança ocorre na próxima renovação.
- **Downgrade pendente:** app deve exibir `pending_plan_slug` e `pending_effective_at` quando vierem na assinatura atual.
- **Avisos de trial:** Starter e Pro = 3 dias; Premium = 7 dias.
- **Cancelamento durante trial:** acesso cortado imediatamente.
- **Cancelamento após cobrança:** mantém acesso até `ends_at`.
- **Reassinatura após expiração:** gera nova assinatura (nova linha).

### 1.3 Funcionalidades Principais

| Funcionalidade | Descricao |
|----------------|-----------|
| **Login** | Autenticacao via Google OAuth ou email/senha |
| **Accounts** | Contas em corretoras para organizar investimentos |
| **Compras** | Registro de compras de ativos (posicao consolidada) |
| **Portfolio** | Nome, investimento mensal, objetivo/meta |
| **Composicao** | Percentual desejado para cada ativo |
| **Crossing** | Comparar ideal vs real e calcular quanto comprar |
| **Historico** | Rastrear ativos removidos com motivos |

### 1.4 Stack Tecnologico

**Backend:**
- PHP 8.2+
- Laravel 12
- Laravel Sanctum (autenticacao API)
- MySQL/MariaDB

**Frontend (consumidor):**
- React Native + Expo
- TypeScript

---

## 2. Arquitetura do Sistema

### 2.1 Estrutura de Diretorios

Referencia principal:
- `app/Http/Controllers/Api`
- `app/Http/Requests`
- `app/Http/Resources`
- `app/Models`
- `app/Services`
- `app/Helpers`
- `app/Policies`
- `database/migrations`
- `database/seeders`
- `routes/api.php`

### 2.2 Diagrama de Dependencias

- Portfolio -> Composition -> CompanyTicker -> Company -> CompanyCategory
- Consolidated -> Account -> User
- User -> Accounts -> Consolidated

---

## 3. Estrutura de Banco de Dados

### 3.1 Visao Geral das Tabelas

| Tabela | Descricao |
|--------|-----------|
| `users` | Usuarios do sistema (login Google ou email/senha) |
| `personal_access_tokens` | Tokens Sanctum |
| `banks` | Bancos/Corretoras disponiveis |
| `accounts` | Contas do usuario em corretoras |
| `company_category` | Categorias de ativos (Acoes, FIIs, ETFs) |
| `companies` | Empresas listadas na bolsa |
| `company_tickers` | Codigos de negociacao (PETR4, HGLG11) |
| `consolidated` | Posicoes reais do usuario (compras) |
| `portfolios` | Carteiras de investimento |
| `portfolio_compositions` | Composicao ideal da carteira |
| `portfolio_composition_histories` | Historico de remocoes |

### 3.2 Migrations

- `users`: `database/migrations/core/2025_01_01_000001_create_users_table.php`
- `personal_access_tokens`: `database/migrations/core/2025_01_01_000004_create_personal_access_tokens_table.php`
- `banks`: `database/migrations/core/2025_01_01_000005_create_banks_table.php`
- `accounts`: `database/migrations/core/2025_01_01_000006_create_accounts_table.php`
- `company_category`: `database/migrations/companies/2025_01_04_000001_create_company_category_table.php`
- `companies`: `database/migrations/companies/2025_01_04_000002_create_companies_table.php`
- `company_tickers`: `database/migrations/companies/2025_01_04_000003_create_company_tickers_table.php`
- `consolidated`: `database/migrations/consolidated/2025_01_06_000001_create_consolidated_table.php`
- `portfolios`: `database/migrations/portfolio/2025_01_08_000001_create_portfolios_table.php`
- `portfolio_compositions`: `database/migrations/portfolio/2025_01_08_000002_create_compositions_table.php`
- `portfolio_composition_histories`: `database/migrations/portfolio/2025_01_08_000003_create_composition_histories_table.php`

### 3.3 Seeders

- `CompanyCategorySeeder`: `database/seeders/CompanyCategorySeeder.php`
- `BankSeeder`: `database/seeders/BankSeeder.php`

---

## 4. Sistema de Autenticacao

### 4.1 Fluxo de Autenticacao (Client-Side OAuth)

1. App React Native inicia login Google localmente (SDK nativo)
2. Google SDK no app abre tela de login
3. Usuario faz login no Google
4. Google retorna id_token para o APP
5. App envia id_token para API Laravel (`POST /api/auth/google`)
6. Backend valida id_token com Google (verifyIdToken)
7. Backend cria/atualiza usuario no banco
8. Backend retorna Bearer token Sanctum para o app

Fluxo alternativo (email/senha):
1. App envia email e senha para API (`POST /api/auth/login`)
2. Backend valida credenciais
3. Backend retorna Bearer token Sanctum para o app

### 4.2 Configuracoes

- `config/services.php`: credenciais Google
- `config/cors.php`: configuracao CORS
- `config/sanctum.php`: Sanctum

### 4.3 Service

- `GoogleAuthService`: `app/Services/Auth/GoogleAuthService.php`

### 4.4 Controller

- `AuthController`: `app/Http/Controllers/Api/AuthController.php`

### 4.5 Requests

- `GoogleAuthRequest`: `app/Http/Requests/Auth/GoogleAuthRequest.php`
- `LoginRequest`: `app/Http/Requests/Auth/LoginRequest.php`
- `UpdateProfileRequest`: `app/Http/Requests/Auth/UpdateProfileRequest.php`
- `UpdatePasswordRequest`: `app/Http/Requests/Auth/UpdatePasswordRequest.php`

### 4.6 Resource

- `UserResource`: `app/Http/Resources/UserResource.php`

### 4.7 Rotas

- `routes/api.php`

---

## 5. Models e Relacionamentos

- `User`: `app/Models/User.php`
- `Bank`: `app/Models/Bank.php`
- `Account`: `app/Models/Account.php`
- `CompanyCategory`: `app/Models/CompanyCategory.php`
- `Company`: `app/Models/Company.php`
- `CompanyTicker`: `app/Models/CompanyTicker.php`
- `Consolidated`: `app/Models/Consolidated.php`
- `Portfolio`: `app/Models/Portfolio.php`
- `Composition`: `app/Models/Composition.php`
- `CompositionHistory`: `app/Models/CompositionHistory.php`

---

## 6. Controllers e Endpoints API

### 6.1 Controllers

- `AuthController`: `app/Http/Controllers/Api/AuthController.php`
- `AccountController`: `app/Http/Controllers/Api/AccountController.php`
- `AssetController`: `app/Http/Controllers/Api/AssetController.php`
- `ConsolidatedController`: `app/Http/Controllers/Api/ConsolidatedController.php`
- `ConsolidatedTransactionController`: `app/Http/Controllers/Api/ConsolidatedTransactionController.php`
- `PortfolioController`: `app/Http/Controllers/Api/PortfolioController.php`
- `CompositionController`: `app/Http/Controllers/Api/CompositionController.php`

### 6.2 Endpoints

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

## 7. Services e Helpers

- `GoogleAuthService`: `app/Services/Auth/GoogleAuthService.php`
- `CrossingService`: `app/Services/Portfolio/CrossingService.php`
- `PortfolioHelper`: `app/Helpers/PortfolioHelper.php`

---

## 8. Regras de Negocio

### 8.1 Regras de Account

- Propriedade: uma account pertence a um unico usuario.
- Default: apenas uma account pode ser default por usuario.
- Exclusao: nao pode excluir account com posicoes ativas.

### 8.2 Regras de Consolidated

- Unicidade: apenas uma posicao por ativo por conta.
- Escopo: validar que a account pertence ao usuario.
- Calculo: `total_purchased = quantity_current * average_purchase_price`.

### 8.3 Regras de Portfolio

- Propriedade: um portfolio pertence a um unico usuario.
- Nome: maximo 80 caracteres.
- Valores: `month_value` e `target_value` >= 0.
- Soft Delete: portfolios removidos marcados com `deleted_at`.

### 8.4 Regras de Composicao

- Unicidade: um ativo aparece apenas uma vez por portfolio.
- Porcentagem: entre 0 e 100.
- Total: soma das porcentagens pode ser != 100%.
- Historico: ao remover, pode salvar no historico com motivo.

### 8.5 Regras de Crossing

- `positioned`: ativo na composicao e tem posicao consolidada.
- `not_positioned`: ativo na composicao e sem posicao.
- `unwind_position`: ativo removido da composicao mas ainda tem posicao.

### 8.6 Calculo de "Quanto Comprar"

- objetivo_ativo = (percentual_ideal x valor_objetivo) / 100
- valor_atual = saldo_atual
- a_comprar = (objetivo_ativo - valor_atual) / ultimo_preco
- resultado = floor(a_comprar) se > 0, senao 0

### 8.7 Regras de Limite (Assinatura)

- Limites sao aplicados por funcionalidade (contas, carteiras, composicoes por carteira, posicoes ativas).
- Em downgrade, itens **acima do limite nao sao removidos**, mas ficam **bloqueados para editar/excluir**.
- Sempre permanecem editaveis os **N mais antigos** (ordenados por `created_at` asc).
- Validacao obrigatoria no backend para:
  - Update/Destroy de contas, carteiras e composicoes.
  - Transacoes de posicao (criar/editar/excluir) considerando:
    - Posicao existente: so editar se estiver entre as mais antigas.
    - Posicao nova: validar limite antes de criar.

---

## 9. Fases de Implementacao

### Status Geral

Todas as fases descritas abaixo estao implementadas no projeto atual.

### Fase 1: Setup Inicial (Concluida)

- [x] Projeto Laravel 12 configurado
- [x] Dependencias instaladas (Sanctum, Google API Client)
- [x] Banco e CORS configurados

### Fase 2: Autenticacao Google OAuth (Concluida)

- [x] Login Google, tokens Sanctum e rotas de auth
- [x] Endpoints de perfil e senha protegidos
- [x] Testes de auth atualizados

### Fase 3: Core (Banks e Accounts) (Concluida)

- [x] Migrations, models, requests, resources, policies e controller
- [x] Endpoints e testes de accounts/banks

### Fase 4: Companies (Categorias e Tickers) (Concluida)

- [x] Migrations, models, seeders, resources e controller
- [x] Endpoints e testes de assets

### Fase 5: Consolidated (Posicoes Reais) (Concluida)

- [x] Migrations, models, accessors, requests, resources e controller
- [x] Endpoints e testes de consolidated/transacoes

### Fase 6: Portfolio (Carteiras Ideais) (Concluida)

- [x] Migrations, models, requests, resources, policies e controller
- [x] Endpoints e testes de portfolios/composicoes

### Fase 7: Crossing (Comparacao Ideal vs Real) (Concluida)

- [x] Helper e service de crossing
- [x] Endpoint e testes de calculo

### Fase 8: Testes e Documentacao (Concluida)

- [x] Suite de testes por modulo
- [x] Documentacao de endpoints e roadmaps

---

## 10. Dependencias e Pacotes

- `composer.json`: dependencias do backend
- `config/services.php`: credenciais Google OAuth

---

## Resumo Executivo

O **DataGrana Portfolio** e uma API REST focada em gestao de carteiras de investimento em **Renda Variavel** com as seguintes caracteristicas:

**Fluxo do Usuario:**
1. Login com Google ou email/senha (cria usuario se nao existe no Google)
2. Criar uma Account (conta na corretora)
3. Cadastrar compras de ativos (posicoes consolidadas)
4. Criar Portfolio com ativos e percentuais
5. Visualizar comparacao (Crossing)

**Entidades Principais:**
- User → Account → Consolidated
- CompanyCategory → Company → CompanyTicker
- User → Portfolio → Composition

**Endpoints Principais:**
- `POST /api/auth/login` - Login
- `POST /api/auth/google` - Login Google
- `GET /api/auth/profile` - Perfil
- `PATCH /api/auth/profile` - Atualizar perfil
- `PUT /api/auth/password` - Atualizar senha
- `GET/POST /api/accounts` - Contas
- `GET/POST /api/consolidated` - Posicoes
- `GET/POST /api/portfolios` - Carteiras
- `GET /api/portfolios/{id}/crossing` - Comparacao
- `GET /api/companies` - Buscar ativos

**Stack:**
- Backend: Laravel 12 + Sanctum
- Banco: MySQL/MariaDB
- Consumidor: React Native + Expo

**Padroes Aplicados:**
- BaseController com `sendResponse`/`sendError`
- Form Requests para validacao
- API Resources para transformacao
- Policies para autorizacao
- Services para logica complexa
