# Roadmap V2 - Core (Banks + Accounts)

> Modulo base: bancos/corretoras e contas do usuario.

**Dependencia:** V1 (Autenticacao) completa

**Nota:** Migrations copiadas do `datagrana-web` (banco compartilhado).

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Seeders](#6-seeders)
7. [Form Requests](#7-form-requests)
8. [Resources](#8-resources)
9. [Policy](#9-policy)
10. [Controller](#10-controller)
11. [Rotas](#11-rotas)
12. [Casos de Teste](#12-casos-de-teste)
13. [Checklist de Implementacao](#13-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a estrutura de contas em corretoras:

- Listar bancos/corretoras disponiveis
- CRUD de contas do usuario
- Apenas uma conta pode ser `default` por usuario
- Conta so pode ser excluida se nao tiver posicoes ativas

**Entregaveis:**
- Tabelas `banks` e `accounts`
- Seeder com corretoras populares
- CRUD completo de accounts
- Testes automatizados

---

## 2. Dependencias

**Requer:** V1 (Autenticacao) completa

**Tabelas necessarias:**
- `users` (da V1)
- `personal_access_tokens` (da V1)

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AccountController.php
│   ├── Requests/
│   │   └── Account/
│   │       ├── StoreAccountRequest.php
│   │       └── UpdateAccountRequest.php
│   └── Resources/
│       ├── BankResource.php
│       └── AccountResource.php
├── Models/
│   ├── Bank.php
│   └── Account.php
└── Policies/
    └── AccountPolicy.php

database/
├── migrations/
│   ├── 2025_01_01_000001_create_banks_table.php
│   └── 2025_01_01_000002_create_accounts_table.php
└── seeders/
    └── BankSeeder.php

tests/
└── Feature/
    ├── Bank/
    │   └── BankListTest.php
    └── Account/
        ├── AccountIndexTest.php
        ├── AccountStoreTest.php
        ├── AccountShowTest.php
        ├── AccountUpdateTest.php
        └── AccountDestroyTest.php
```

---

## 4. Migrations

### 4.1 Migration: banks

**Arquivo:** `database/migrations/core/2025_01_01_000005_create_banks_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

### 4.2 Migration: accounts

**Arquivo:** `database/migrations/core/2025_01_01_000006_create_accounts_table.php`

**Importante:** Copiado do `datagrana-web`. Ja executado no banco compartilhado.

**Unicidade:** Ajustada via `database/migrations/core/2025_01_20_000001_update_accounts_unique_index.php` para `unique(['user_id', 'account'])`.

---

## 5. Models

### 5.1 Bank Model

**Arquivo:** `app/Models/Bank.php`

Implementado em `app/Models/Bank.php`.

### 5.2 Account Model

**Arquivo:** `app/Models/Account.php`

Implementado em `app/Models/Account.php`.

### 5.3 Atualizar User Model

**Arquivo:** `app/Models/User.php` (adicionar relacionamento)

Implementado em `app/Models/User.php`.

---

## 6. Seeders

### 6.1 BankSeeder

**Arquivo:** `database/seeders/BankSeeder.php`

Implementado em `database/seeders/BankSeeder.php`.

### 6.2 DatabaseSeeder

**Arquivo:** `database/seeders/DatabaseSeeder.php`

Implementado em `database/seeders/DatabaseSeeder.php`.

---

## 7. Form Requests

### 7.1 StoreAccountRequest

**Arquivo:** `app/Http/Requests/Account/StoreAccountRequest.php`

Implementado em `app/Http/Requests/Account/StoreAccountRequest.php`.

### 7.2 UpdateAccountRequest

**Arquivo:** `app/Http/Requests/Account/UpdateAccountRequest.php`

Implementado em `app/Http/Requests/Account/UpdateAccountRequest.php`.

---

## 8. Resources

### 8.1 BankResource

**Arquivo:** `app/Http/Resources/BankResource.php`

Implementado em `app/Http/Resources/BankResource.php`.

### 8.2 AccountResource

**Arquivo:** `app/Http/Resources/AccountResource.php`

Implementado em `app/Http/Resources/AccountResource.php`.

---

## 9. Policy

### 9.1 AccountPolicy

**Arquivo:** `app/Policies/AccountPolicy.php`

Implementado em `app/Policies/AccountPolicy.php`.

### 9.2 Registrar Policy

**Arquivo:** `app/Providers/AppServiceProvider.php`

Implementado em `app/Providers/AppServiceProvider.php`.

---

## 10. Controller

### 10.1 AccountController

**Arquivo:** `app/Http/Controllers/Api/AccountController.php`

Implementado em `app/Http/Controllers/Api/AccountController.php`.

---

## 11. Rotas

### 11.1 Atualizar routes/api.php

Implementado em `routes/api.php`.

---

## 12. Casos de Teste

### 12.1 Factories

**Arquivo:** `database/factories/BankFactory.php`

Implementado em `database/factories/BankFactory.php`.

**Arquivo:** `database/factories/AccountFactory.php`

Implementado em `database/factories/AccountFactory.php`.

### 12.2 BankListTest

**Arquivo:** `tests/Feature/Bank/BankListTest.php`

Implementado em `tests/Feature/Bank/BankListTest.php`.

### 12.3 AccountIndexTest

**Arquivo:** `tests/Feature/Account/AccountIndexTest.php`

Implementado em `tests/Feature/Account/AccountIndexTest.php`.

### 12.4 AccountStoreTest

**Arquivo:** `tests/Feature/Account/AccountStoreTest.php`

Implementado em `tests/Feature/Account/AccountStoreTest.php`.

### 12.5 AccountShowTest

**Arquivo:** `tests/Feature/Account/AccountShowTest.php`

Implementado em `tests/Feature/Account/AccountShowTest.php`.

### 12.6 AccountUpdateTest

**Arquivo:** `tests/Feature/Account/AccountUpdateTest.php`

Implementado em `tests/Feature/Account/AccountUpdateTest.php`.

### 12.7 AccountDestroyTest

**Arquivo:** `tests/Feature/Account/AccountDestroyTest.php`

Implementado em `tests/Feature/Account/AccountDestroyTest.php`.

---

## 13. Checklist de Implementacao

### 13.1 Database

- [x] Criar migration `banks`
- [x] Criar migration `accounts`
- [x] Rodar `php artisan migrate`
- [x] Criar `BankFactory`
- [x] Criar `AccountFactory`
- [x] Criar `BankSeeder`
- [x] Rodar `php artisan db:seed`

### 13.2 Models

- [x] Criar `Bank` model
- [x] Criar `Account` model
- [x] Atualizar `User` model (adicionar relacionamento)

### 13.3 Backend

- [x] Criar `BankResource`
- [x] Criar `AccountResource`
- [x] Criar `StoreAccountRequest`
- [x] Criar `UpdateAccountRequest`
- [x] Criar `AccountPolicy`
- [x] Registrar Policy no `AppServiceProvider`
- [x] Criar `AccountController`
- [x] Configurar rotas

### 13.4 Testes

- [x] Criar `BankListTest`
- [x] Criar `AccountIndexTest`
- [x] Criar `AccountStoreTest`
- [x] Criar `AccountShowTest`
- [x] Criar `AccountUpdateTest`
- [x] Criar `AccountDestroyTest`
- [x] Rodar `php artisan test` - todos passando

### 13.5 Validacao Final

- [x] Testar `GET /api/banks`
- [x] Testar `GET /api/accounts`
- [x] Testar `POST /api/accounts`
- [x] Testar `GET /api/accounts/{id}`
- [x] Testar `PUT /api/accounts/{id}`
- [x] Testar `DELETE /api/accounts/{id}`

---

## Endpoints da V2

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/banks` | Sim | Lista corretoras |
| GET | `/api/accounts` | Sim | Lista contas do usuario |
| POST | `/api/accounts` | Sim | Cria nova conta |
| GET | `/api/accounts/{id}` | Sim | Detalhes da conta |
| PUT | `/api/accounts/{id}` | Sim | Atualiza conta |
| DELETE | `/api/accounts/{id}` | Sim | Remove conta |

---

## Proxima Fase

Apos completar a V2, prosseguir para:
- **V3 - Companies**: Categorias, empresas e tickers (ativos de renda variavel)
