# Roadmap V5 - Portfolio (Carteiras Ideais)

> Carteiras de investimento e composicoes com alocacao percentual.

**Dependencia:** V4 completa. **Migrations copiadas** do `datagrana-web` (banco compartilhado).

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Form Requests](#6-form-requests)
7. [Resources](#7-resources)
8. [Policy](#8-policy)
9. [Controllers](#9-controllers)
10. [Rotas](#10-rotas)
11. [Casos de Teste](#11-casos-de-teste)
12. [Checklist de Implementacao](#12-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o sistema de carteiras ideais:

- CRUD de portfolios
- Composicoes com percentual de alocacao (renda fixa e renda variavel)
- Historico de ativos removidos
- Comparacao por categoria (percentual por categoria)

**Entregaveis:**
- Tabelas `portfolios`, `compositions`, `composition_histories`
- CRUD completo de portfolio e composicoes
- Testes automatizados

## Regras de Validação de Composição

### Tipo de Ativo
- `type` deve ser `treasure` ou `company`
- `asset_id` representa o `treasure_id` ou `company_ticker_id` conforme o tipo
- Apenas um tipo por composicao

### Soma dos Percentuais
- **Flexível:** Permite soma ≠ 100%
- **Warning:** Exibe aviso se soma ≠ 100% (não bloqueia)
- Permite ajustes graduais da carteira

### Percentual Individual
- Deve ser **maior ou igual a 0** (`percentage >= 0`)
- Permite percentual zero para ajustes

### Duplicatas
- Sem constraint no banco para impedir duplicatas
- O app deve evitar ativos duplicados na carteira

---

## 2. Dependencias

**Requer:** V4 (Consolidated) completa

**Tabelas necessarias:**
- `users`
- `company_tickers`
- `treasures`
- `consolidated`

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── PortfolioController.php
│   │       └── CompositionController.php
│   ├── Requests/
│   │   └── Portfolio/
│   │       ├── StorePortfolioRequest.php
│   │       ├── UpdatePortfolioRequest.php
│   │       ├── StoreCompositionRequest.php
│   │       ├── UpdateCompositionRequest.php
│   │       └── UpdateCompositionBatchRequest.php
│   └── Resources/
│       ├── PortfolioResource.php
│       ├── CompositionResource.php
│       └── CompositionHistoryResource.php
│       ├── TreasureResource.php
│       └── TreasureCategoryResource.php
├── Models/
│   ├── Portfolio.php
│   ├── Composition.php
│   └── CompositionHistory.php
└── Policies/
    └── PortfolioPolicy.php

database/
├── migrations/
│   └── portfolio/
│       ├── 2025_01_08_000001_create_portfolios_table.php
│       ├── 2025_01_08_000002_create_compositions_table.php
│       └── 2025_01_08_000003_create_composition_histories_table.php
└── factories/
    ├── PortfolioFactory.php
    ├── CompositionFactory.php
    └── CompositionHistoryFactory.php

tests/
└── Feature/
    └── Portfolio/
        ├── PortfolioIndexTest.php
        ├── PortfolioStoreTest.php
        ├── PortfolioUpdateTest.php
        ├── PortfolioDestroyTest.php
        ├── CompositionStoreTest.php
        ├── CompositionUpdateTest.php
        ├── CompositionUpdateBatchTest.php
        └── CompositionDestroyTest.php
```

---

## 4. Migrations

### 4.1 Migration: portfolios

**Arquivo:** `database/migrations/portfolio/2025_01_08_000001_create_portfolios_table.php`

### 4.2 Migration: compositions

**Arquivo:** `database/migrations/portfolio/2025_01_08_000002_create_compositions_table.php`

### 4.3 Migration: composition_histories

**Arquivo:** `database/migrations/portfolio/2025_01_08_000003_create_composition_histories_table.php`

---

## 5. Models

### 5.1 Portfolio Model

Implementado em `app/Models/Portfolio.php`.

### 5.2 Composition Model

Implementado em `app/Models/Composition.php`.

### 5.3 CompositionHistory Model

Implementado em `app/Models/CompositionHistory.php`.

### 5.4 Atualizar User Model

Relacionamento `portfolios()` em `app/Models/User.php`.

---

## 6. Form Requests

- `app/Http/Requests/Portfolio/StorePortfolioRequest.php`
- `app/Http/Requests/Portfolio/UpdatePortfolioRequest.php`
- `app/Http/Requests/Portfolio/StoreCompositionRequest.php`
- `app/Http/Requests/Portfolio/UpdateCompositionRequest.php`
- `app/Http/Requests/Portfolio/UpdateCompositionBatchRequest.php`

---

## 7. Resources

- `app/Http/Resources/PortfolioResource.php`
- `app/Http/Resources/CompositionResource.php`
- `app/Http/Resources/CompositionHistoryResource.php`
- `app/Http/Resources/TreasureResource.php`
- `app/Http/Resources/TreasureCategoryResource.php`

---

## 8. Policy

- `app/Policies/PortfolioPolicy.php`
- Registro em `app/Providers/AppServiceProvider.php`

---

## 9. Controllers

### 9.1 PortfolioController

Implementado em `app/Http/Controllers/Api/PortfolioController.php`.

### 9.2 CompositionController

Implementado em `app/Http/Controllers/Api/CompositionController.php`.

---

## 10. Rotas

Rotas implementadas em `routes/api.php`.

---

## 11. Casos de Teste

- `tests/Feature/Portfolio/PortfolioIndexTest.php`
- `tests/Feature/Portfolio/PortfolioStoreTest.php`
- `tests/Feature/Portfolio/PortfolioUpdateTest.php`
- `tests/Feature/Portfolio/PortfolioDestroyTest.php`
- `tests/Feature/Portfolio/CompositionStoreTest.php`
- `tests/Feature/Portfolio/CompositionUpdateTest.php`
- `tests/Feature/Portfolio/CompositionUpdateBatchTest.php`
- `tests/Feature/Portfolio/CompositionDestroyTest.php`

---

## 12. Checklist de Implementacao

### 12.1 Database

- [x] Criar migration `portfolios`
- [x] Criar migration `compositions`
- [x] Criar migration `composition_histories`
- [ ] Rodar `php artisan migrate`
- [x] Criar `PortfolioFactory`
- [x] Criar `CompositionFactory`
- [x] Criar `CompositionHistoryFactory`

### 12.2 Models

- [x] Criar `Portfolio` model
- [x] Criar `Composition` model
- [x] Criar `CompositionHistory` model
- [x] Atualizar `User` model (relacionamento)

### 12.3 Backend

- [x] Criar Form Requests (Store/Update Portfolio, Store/Update Composition, UpdateCompositionBatch)
- [x] Criar `PortfolioResource`
- [x] Criar `CompositionResource`
- [x] Criar `CompositionHistoryResource`
- [x] Criar `TreasureResource`
- [x] Criar `TreasureCategoryResource`
- [x] Criar `PortfolioPolicy`
- [x] Registrar Policy
- [x] Criar `PortfolioController`
- [x] Criar `CompositionController`
- [x] Configurar rotas

### 12.4 Testes

- [x] Criar `PortfolioIndexTest`
- [x] Criar `PortfolioStoreTest`
- [x] Criar `PortfolioUpdateTest`
- [x] Criar `PortfolioDestroyTest`
- [x] Criar `CompositionStoreTest`
- [x] Criar `CompositionUpdateTest`
- [x] Criar `CompositionUpdateBatchTest`
- [x] Criar `CompositionDestroyTest`
- [ ] Rodar `php artisan test` - todos passando

### 12.5 Validacao Final

- [ ] Testar `GET /api/portfolios`
- [ ] Testar `POST /api/portfolios`
- [ ] Testar `GET /api/portfolios/{id}`
- [ ] Testar `PUT /api/portfolios/{id}`
- [ ] Testar `DELETE /api/portfolios/{id}`
- [ ] Testar `POST /api/portfolios/{id}/compositions`
- [ ] Testar `PUT /api/compositions/{id}`
- [ ] Testar `PUT /api/compositions/batch`
- [ ] Testar `DELETE /api/compositions/{id}`

---

## Endpoints da V5

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios` | Sim | Lista portfolios |
| POST | `/api/portfolios` | Sim | Cria portfolio |
| GET | `/api/portfolios/{id}` | Sim | Detalhes |
| PUT | `/api/portfolios/{id}` | Sim | Atualiza portfolio |
| DELETE | `/api/portfolios/{id}` | Sim | Remove portfolio |
| POST | `/api/portfolios/{id}/compositions` | Sim | Adiciona ativos |
| PUT | `/api/compositions/{id}` | Sim | Atualiza % |
| PUT | `/api/compositions/batch` | Sim | Atualiza multiplos |
| DELETE | `/api/compositions/{id}` | Sim | Remove ativo |

---

## Proxima Fase

Apos completar a V5, prosseguir para:
- **V6 - Crossing**: Comparacao entre portfolio ideal e posicoes reais
