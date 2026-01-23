# Roadmap V4 - Consolidated (Posicoes Reais)

> Registro de compras e posicoes consolidadas do usuario.

**Dependencia:** V3 completa. **Migrations copiadas** do `datagrana-web` (banco compartilhado).

---

## Status atual

- ✅ Consolidated + transacoes implementados (create, update, delete, summary).
- ✅ Regras de consolidacao replicadas do datagrana-web (`ConsolidationService`).
- Arquivos principais:
  - `app/Http/Controllers/Api/ConsolidatedController.php`
  - `app/Http/Controllers/Api/ConsolidatedTransactionController.php`
  - `app/Services/Application/Consolidated/ConsolidationService.php`
  - `app/Http/Requests/Consolidated/*`
  - `routes/api.php`

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migration](#4-migration)
5. [Model](#5-model)
6. [Form Requests](#6-form-requests)
7. [Resource](#7-resource)
8. [Policy](#8-policy)
9. [Controller](#9-controller)
10. [Rotas](#10-rotas)
11. [Casos de Teste](#11-casos-de-teste)
12. [Checklist de Implementacao](#12-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o registro de posicoes consolidadas:

- Registrar transacoes de compra/venda por conta
- Consolidar posicoes por ativo (company/treasure)
- Calcular saldo atual (balance), lucro (profit) e % de lucro
- Uma posicao por ativo por conta
- Resumo geral das posicoes

**Entregaveis:**
- Tabela `consolidated`
- Tabelas `company_transactions` e `treasure_transaction`
- Service de consolidacao
- Endpoints de transacoes e resumo
- Endpoint de resumo
- Testes automatizados

---

## Regras de Negócio

### Cálculo de Balance (Lucro/Prejuízo)
Balance é calculado a partir do preço atual do ativo e da quantidade atual.

### Fechamento de Posição
- Quando `quantity` chega a 0 após venda total
- `closed = true` quando quantidade zera
- Consolidado só é removido quando não há mais transações vinculadas

### Venda Parcial
- Recalcula `quantity` (subtrai quantidade vendida)
- **Mantém `average_price` original** (preço médio de compra)
- Atualiza `current_price` e `balance`

### Venda sem saldo
- Bloqueia venda quando `quantity_current` for insuficiente
- Retorna erro detalhado com o ativo e quantidade disponivel

---

## 2. Dependencias

**Requer:** V3 (Companies) completa

**Tabelas necessarias:**
- `users`
- `accounts`
- `company_tickers`
- `consolidated`
- `company_transactions`
- `treasure_transaction`
- `treasures`

---

## 3. Estrutura de Arquivos

Estrutura principal:
- `app/Http/Controllers/Api/ConsolidatedController.php`
- `app/Http/Controllers/Api/ConsolidatedTransactionController.php`
- `app/Http/Requests/Consolidated/*`
- `app/Http/Resources/ConsolidatedResource.php`
- `app/Models/Consolidated.php`, `CompanyTransaction.php`, `TreasureTransaction.php`, `Treasure.php`, `TreasureCategory.php`
- `app/Services/Application/Consolidated/ConsolidationService.php`
- `app/Policies/ConsolidatedPolicy.php`
- `app/Exceptions/InsufficientAssetException.php`
- `database/migrations/consolidated/*`
- `database/factories/*`
- `tests/Feature/Consolidated/*`

---

## 4. Migration

### 4.1 Migration: consolidated

**Arquivo:** `database/migrations/consolidated/2025_01_06_000001_create_consolidated_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

### 4.2 Migration: treasure_transaction

**Arquivo:** `database/migrations/consolidated/2025_01_06_000002_create_treasure_transaction_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

### 4.3 Migration: company_transactions

**Arquivo:** `database/migrations/consolidated/2025_01_06_000004_create_company_transactions_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

---

## 5. Model

### 5.1 Consolidated Model

**Arquivo:** `app/Models/Consolidated.php`

Implementado em `app/Models/Consolidated.php`.

### 5.2 CompanyTransaction Model

**Arquivo:** `app/Models/CompanyTransaction.php`

Implementado em `app/Models/CompanyTransaction.php`.

### 5.3 TreasureTransaction Model

**Arquivo:** `app/Models/TreasureTransaction.php`

Implementado em `app/Models/TreasureTransaction.php`.

### 5.4 Treasure e TreasureCategory Models

**Arquivos:** `app/Models/Treasure.php`, `app/Models/TreasureCategory.php`

Implementados em `app/Models/Treasure.php` e `app/Models/TreasureCategory.php`.

### 5.5 Earning e UserNetBalance Models

**Arquivos:** `app/Models/Earning.php`, `app/Models/UserNetBalance.php`

Implementados em `app/Models/Earning.php` e `app/Models/UserNetBalance.php`.

### 5.2 Atualizar Account Model

**Arquivo:** `app/Models/Account.php` (atualizar metodo)
Implementado em `app/Models/Account.php`.

---

## 6. Form Requests

### 6.1 StoreTransactionRequest

**Arquivo:** `app/Http/Requests/Consolidated/StoreTransactionRequest.php`

Implementado em `app/Http/Requests/Consolidated/StoreTransactionRequest.php`.

### 6.2 UpdateTransactionRequest

**Arquivo:** `app/Http/Requests/Consolidated/UpdateTransactionRequest.php`

Implementado em `app/Http/Requests/Consolidated/UpdateTransactionRequest.php`.

---

## 7. Resource

### 7.1 ConsolidatedResource

**Arquivo:** `app/Http/Resources/ConsolidatedResource.php`

Implementado em `app/Http/Resources/ConsolidatedResource.php`.

### 7.2 CompanyTransactionResource

**Arquivo:** `app/Http/Resources/CompanyTransactionResource.php`

Implementado em `app/Http/Resources/CompanyTransactionResource.php`.

### 7.3 TreasureTransactionResource

**Arquivo:** `app/Http/Resources/TreasureTransactionResource.php`

Implementado em `app/Http/Resources/TreasureTransactionResource.php`.

---

## 8. Policy

### 8.1 ConsolidatedPolicy

**Arquivo:** `app/Policies/ConsolidatedPolicy.php`

Implementado em `app/Policies/ConsolidatedPolicy.php`.

### 8.2 Registrar Policy

**Arquivo:** `app/Providers/AppServiceProvider.php` (adicionar)

Implementado em `app/Providers/AppServiceProvider.php`.

---

## 9. Controller

### 9.1 ConsolidatedController

**Arquivo:** `app/Http/Controllers/Api/ConsolidatedController.php`

Implementado em `app/Http/Controllers/Api/ConsolidatedController.php`.

### 9.2 ConsolidatedTransactionController

**Arquivo:** `app/Http/Controllers/Api/ConsolidatedTransactionController.php`

Implementado em `app/Http/Controllers/Api/ConsolidatedTransactionController.php`.

### 9.3 ConsolidationService

**Arquivo:** `app/Services/Consolidated/ConsolidationService.php`

Implementado em `app/Services/Consolidated/ConsolidationService.php`.

### 9.4 InsufficientAssetException

**Arquivo:** `app/Exceptions/InsufficientAssetException.php`

Implementado em `app/Exceptions/InsufficientAssetException.php`.

---

## 10. Rotas

### 10.1 Atualizar routes/api.php

Implementado em `routes/api.php`.

---

## 11. Casos de Teste

### 11.1 Factories

**Arquivos:**
- `database/factories/ConsolidatedFactory.php`
- `database/factories/CompanyTransactionFactory.php`
- `database/factories/TreasureTransactionFactory.php`
- `database/factories/TreasureCategoryFactory.php`
- `database/factories/TreasureFactory.php`

Implementados nos arquivos acima.

### 11.2 ConsolidatedIndexTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedIndexTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedIndexTest.php`.

### 11.3 ConsolidatedTransactionStoreTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedTransactionStoreTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedTransactionStoreTest.php`.

### 11.4 ConsolidatedShowTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedShowTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedShowTest.php`.

### 11.5 ConsolidatedSummaryTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`.

### 11.6 ConsolidatedTransactionUpdateTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedTransactionUpdateTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedTransactionUpdateTest.php`.

### 11.7 ConsolidatedTransactionDestroyTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedTransactionDestroyTest.php`

Implementado em `tests/Feature/Consolidated/ConsolidatedTransactionDestroyTest.php`.

---

## 12. Checklist de Implementacao

### 12.1 Database

- [x] Criar migration `consolidated`
- [x] Criar migration `treasure_transaction`
- [x] Criar migration `company_transactions`
- [x] Rodar `php artisan migrate`
- [x] Criar `ConsolidatedFactory`
- [x] Criar `CompanyTransactionFactory`
- [x] Criar `TreasureTransactionFactory`
- [x] Criar `TreasureCategoryFactory`
- [x] Criar `TreasureFactory`

### 12.2 Models

- [x] Criar `Consolidated` model
- [x] Criar `CompanyTransaction` model
- [x] Criar `TreasureTransaction` model
- [x] Criar `Treasure` model
- [x] Criar `TreasureCategory` model
- [x] Criar `Earning` model
- [x] Criar `UserNetBalance` model
- [x] Atualizar `Account` model (hasActivePositions)

### 12.3 Backend

- [x] Criar `StoreTransactionRequest`
- [x] Criar `UpdateTransactionRequest`
- [x] Criar `ConsolidatedResource`
- [x] Criar `CompanyTransactionResource`
- [x] Criar `TreasureTransactionResource`
- [x] Criar `ConsolidatedPolicy`
- [x] Registrar Policy
- [x] Criar `ConsolidatedController`
- [x] Criar `ConsolidatedTransactionController`
- [x] Criar `ConsolidationService`
- [x] Criar `InsufficientAssetException`
- [x] Configurar rotas

### 12.4 Testes

- [x] Criar `ConsolidatedIndexTest`
- [x] Criar `ConsolidatedShowTest`
- [x] Criar `ConsolidatedSummaryTest`
- [x] Criar `ConsolidatedTransactionStoreTest`
- [x] Criar `ConsolidatedTransactionUpdateTest`
- [x] Criar `ConsolidatedTransactionDestroyTest`
- [x] Rodar `php artisan test` - todos passando

### 12.5 Validacao Final

- [x] Testar `GET /api/consolidated`
- [x] Testar `GET /api/consolidated/{id}`
- [x] Testar `GET /api/consolidated/summary`
- [x] Testar `POST /api/consolidated/transactions`
- [x] Testar `PUT /api/consolidated/transactions/{type}/{id}`
- [x] Testar `DELETE /api/consolidated/transactions/{type}/{id}`

---

## Endpoints da V4

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/consolidated` | Sim | Lista posicoes |
| GET | `/api/consolidated/{id}` | Sim | Detalhes da posicao |
| GET | `/api/consolidated/summary` | Sim | Resumo geral |
| POST | `/api/consolidated/transactions` | Sim | Cria transacoes (company/treasure) |
| PUT | `/api/consolidated/transactions/{type}/{id}` | Sim | Atualiza transacao |
| DELETE | `/api/consolidated/transactions/{type}/{id}` | Sim | Remove transacao |

---

## Proxima Fase

Apos completar a V4, prosseguir para:
- **V5 - Portfolio**: Carteiras ideais e composicoes
