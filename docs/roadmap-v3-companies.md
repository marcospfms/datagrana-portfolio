# Roadmap V3 - Companies (Ativos de Renda Variavel)

> Estrutura de categorias, empresas e tickers para ativos de renda variavel.

> **Nota Importante:** As migrations desta fase foram copiadas do projeto `datagrana-web`, pois ambos os projetos compartilham o mesmo banco de dados. As migrations já foram executadas no banco compartilhado.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Seeders](#6-seeders)
7. [Resources](#7-resources)
8. [Controller](#8-controller)
9. [Rotas](#9-rotas)
10. [Casos de Teste](#10-casos-de-teste)
11. [Checklist de Implementacao](#11-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a estrutura de ativos de renda variavel:

- Categorias: Acoes, FIIs, ETFs
- Empresas: Petrobras, Vale, HGLG11, etc.
- Tickers: PETR4, VALE3, HGLG11

**Entregaveis:**
- Tabelas `company_category`, `companies`, `company_tickers`
- Seeders com categorias
- Endpoint de busca de ativos
- Testes automatizados

---

## 2. Dependencias

**Requer:** V2 (Core) completa

**Tabelas necessarias:**
- `users`
- `banks`
- `accounts`

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AssetController.php
│   └── Resources/
│       ├── CompanyCategoryResource.php
│       ├── CompanyResource.php
│       └── CompanyTickerResource.php
└── Models/
    ├── Coin.php
    ├── CompanyCategory.php
    ├── Company.php
    └── CompanyTicker.php

database/
├── migrations/
│   └── companies/
│       ├── 2025_01_04_000001_create_company_category_table.php
│       ├── 2025_01_04_000002_create_companies_table.php
│       └── 2025_01_04_000003_create_company_tickers_table.php
└── seeders/
    ├── CoinSeeder.php
    └── CompanyCategorySeeder.php

database/
└── factories/
    ├── CoinFactory.php
    ├── CompanyCategoryFactory.php
    ├── CompanyFactory.php
    └── CompanyTickerFactory.php

tests/
└── Feature/
    └── Asset/
        ├── AssetCategoriesTest.php
        ├── AssetSearchTest.php
        └── AssetShowTest.php
```

---

## 4. Migrations

### 4.1 Migration: company_category

**Arquivo:** `database/migrations/companies/2025_01_04_000001_create_company_category_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

**Observacao:** inclui `coin_id` (FK para `coins`).

### 4.2 Migration: companies

**Arquivo:** `database/migrations/companies/2025_01_04_000002_create_companies_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

### 4.3 Migration: company_tickers

**Arquivo:** `database/migrations/companies/2025_01_04_000003_create_company_tickers_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

---

## 5. Models

### 5.1 CompanyCategory Model

**Arquivo:** `app/Models/CompanyCategory.php`

Implementado em `app/Models/CompanyCategory.php`.

### 5.2 Company Model

**Arquivo:** `app/Models/Company.php`

Implementado em `app/Models/Company.php`.

### 5.3 CompanyTicker Model

**Arquivo:** `app/Models/CompanyTicker.php`

Implementado em `app/Models/CompanyTicker.php`.

### 5.4 Coin Model (Suporte ao coin_id)

**Arquivo:** `app/Models/Coin.php`

Implementado em `app/Models/Coin.php`.

---

## 6. Seeders

### 6.1 CoinSeeder

**Arquivo:** `database/seeders/CoinSeeder.php`

Implementado em `database/seeders/CoinSeeder.php`.

### 6.2 CompanyCategorySeeder

**Arquivo:** `database/seeders/CompanyCategorySeeder.php`

Implementado em `database/seeders/CompanyCategorySeeder.php`.

### 6.3 Atualizar DatabaseSeeder

**Arquivo:** `database/seeders/DatabaseSeeder.php`

Implementado em `database/seeders/DatabaseSeeder.php`.

---

## 7. Resources

### 7.1 CompanyCategoryResource

**Arquivo:** `app/Http/Resources/CompanyCategoryResource.php`

Implementado em `app/Http/Resources/CompanyCategoryResource.php`.

### 7.2 CompanyResource

**Arquivo:** `app/Http/Resources/CompanyResource.php`

Implementado em `app/Http/Resources/CompanyResource.php`.

### 7.3 CompanyTickerResource

**Arquivo:** `app/Http/Resources/CompanyTickerResource.php`

Implementado em `app/Http/Resources/CompanyTickerResource.php`.

---

## 8. Controller

### 8.1 AssetController

**Arquivo:** `app/Http/Controllers/Api/AssetController.php`

Implementado em `app/Http/Controllers/Api/AssetController.php`.

---

## 9. Rotas

### 9.1 Atualizar routes/api.php

Implementado em `routes/api.php`.

---

## 10. Casos de Teste

### 10.1 Factories

**Arquivo:** `database/factories/CompanyCategoryFactory.php`

Implementado em `database/factories/CompanyCategoryFactory.php`.

**Arquivo:** `database/factories/CompanyFactory.php`

Implementado em `database/factories/CompanyFactory.php`.

**Arquivo:** `database/factories/CompanyTickerFactory.php`

Implementado em `database/factories/CompanyTickerFactory.php`.

### 10.2 AssetCategoriesTest

**Arquivo:** `tests/Feature/Asset/AssetCategoriesTest.php`

Implementado em `tests/Feature/Asset/AssetCategoriesTest.php`.

### 10.3 AssetSearchTest

**Arquivo:** `tests/Feature/Asset/AssetSearchTest.php`

Implementado em `tests/Feature/Asset/AssetSearchTest.php`.

### 10.4 AssetShowTest

**Arquivo:** `tests/Feature/Asset/AssetShowTest.php`

Implementado em `tests/Feature/Asset/AssetShowTest.php`.

---

## 11. Checklist de Implementacao

### 11.1 Database

- [x] Criar migration `company_category`
- [x] Criar migration `companies`
- [x] Criar migration `company_tickers`
- [x] Rodar `php artisan migrate`
- [x] Criar `CoinFactory`
- [x] Criar `CompanyCategoryFactory`
- [x] Criar `CompanyFactory`
- [x] Criar `CompanyTickerFactory`
- [x] Criar `CoinSeeder`
- [x] Criar `CompanyCategorySeeder`
- [x] Atualizar `DatabaseSeeder`
- [x] Rodar `php artisan db:seed`

### 11.2 Models

- [x] Criar `Coin` model
- [x] Criar `CompanyCategory` model
- [x] Criar `Company` model
- [x] Criar `CompanyTicker` model

### 11.3 Backend

- [x] Criar `CompanyCategoryResource`
- [x] Criar `CompanyResource`
- [x] Criar `CompanyTickerResource`
- [x] Criar `AssetController`
- [x] Configurar rotas

### 11.4 Testes

- [x] Criar `AssetCategoriesTest`
- [x] Criar `AssetSearchTest`
- [x] Criar `AssetShowTest`
- [x] Rodar `php artisan test` - todos passando

### 11.5 Validacao Final

- [x] Testar `GET /api/companies/categories`
- [x] Testar `GET /api/companies?search=XXX`
- [x] Testar `GET /api/companies?search=XXX&category_id=X`
- [x] Testar `GET /api/companies/popular`
- [x] Testar `GET /api/companies/{id}`

---

## Endpoints da V3

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/companies/categories` | Sim | Lista categorias |
| GET | `/api/companies?search=` | Sim | Busca ativos |
| GET | `/api/companies/popular` | Sim | Ativos populares |
| GET | `/api/companies/{id}` | Sim | Detalhes do ativo |

---

## Proxima Fase

Apos completar a V3, prosseguir para:
- **V4 - Consolidated**: Posicoes reais do usuario (compras de ativos)
