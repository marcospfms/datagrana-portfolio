# Roadmap V1 - Setup + Autenticacao Google OAuth

> Fase inicial: implementacao da autenticacao via Google OAuth com Sanctum no projeto existente.

---

## Contexto do Projeto

Este roadmap assume que o projeto **datagrana-portfolio** ja existe e esta configurado:

- **Laravel 12** ja instalado e configurado
- **Sanctum** ja instalado (migrations publicadas)
- **Banco de dados compartilhado** com `datagrana-web` (mesmo database)
- **API-only**: Ignora rotas web, Fortify, Breeze e Inertia (mantidos instalados para uso futuro)
- **Migrations copiadas** do datagrana-web (ja executadas no banco)

### Estrategia de Migracao

```
datagrana-web (atual) → sera deprecated
                ↓
datagrana-portfolio (novo) → substitui completamente
```

O **datagrana-portfolio** duplicara Models, Controllers e Services do `datagrana-web`, mas focando exclusivamente em API REST para consumo mobile.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Setup do Projeto](#3-setup-do-projeto)
4. [Estrutura de Arquivos](#4-estrutura-de-arquivos)
5. [Migration e Model](#5-migration-e-model)
6. [Service de Autenticacao](#6-service-de-autenticacao)
7. [Controller e Requests](#7-controller-e-requests)
8. [Resource](#8-resource)
9. [Rotas](#9-rotas)
10. [Casos de Teste](#10-casos-de-teste)
11. [Checklist de Implementacao](#11-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o sistema de autenticacao completo usando Google OAuth **client-side**:

### Fluxo de Autenticacao (Client-Side OAuth)

```
1. App React Native inicia login Google localmente (SDK nativo)
   ↓
2. Google SDK no app abre tela de login
   ↓
3. Usuario faz login no Google
   ↓
4. Google retorna id_token para o APP
   ↓
5. App envia id_token para API Laravel (POST /api/auth/google)
   ↓
6. Backend valida id_token com Google (verifyIdToken)
   ↓
7. Backend cria/atualiza usuario no banco
   ↓
8. Backend retorna Bearer token Sanctum para o app
```

**Importante:**
- ❌ Nao ha callback/redirect server-side
- ❌ Nao usa OAuth code flow
- ✅ Apenas aceita `id_token` ja emitido pelo Google
- ✅ Backend apenas valida token e retorna Sanctum token

**Entregaveis:**
- Autenticacao Google OAuth funcional (client-side)
- Endpoints: login, me, profile, password, logout, logout-all
- Testes automatizados
- Integracao com banco existente

---

## 2. Dependencias

### 2.1 Pacotes Composer

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "google/apiclient": "^2.0"
    },
    "require-dev": {
        "laravel/pint": "^1.0",
        "phpunit/phpunit": "^11.0"
    }
}
```

### 2.2 Servicos Externos

- **Google Cloud Console**: Credenciais OAuth 2.0
- **MySQL/MariaDB**: Banco de dados

---

## 3. Setup do Projeto

### 3.1 Verificar Instalacao

O projeto ja existe. Verifique se as dependencias necessarias estao instaladas:

```bash
cd datagrana-portfolio

# Verificar pacotes instalados
composer show laravel/sanctum
composer show google/apiclient

# Se algum pacote estiver faltando, instale:
composer require google/apiclient
```

### 3.2 Configurar .env

**Importante:** O banco de dados e **compartilhado** com `datagrana-web`.

```env
APP_NAME="DataGrana Portfolio"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de Dados (MESMO banco do datagrana-web)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=datagrana
DB_USERNAME=root
DB_PASSWORD=

# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### 3.3 Configurar config/services.php

Implementado em `config/services.php`.

### 3.4 Configurar CORS (config/cors.php)

Para mobile com Bearer token, a configuracao pode ser simplificada, mas mantemos completa para uso futuro:

Implementado em `config/cors.php`.

### 3.5 Configurar Sanctum (config/sanctum.php)

Mantemos configuracao completa (stateful/CSRF) para uso futuro, mas mobile usa apenas Bearer tokens:

Implementado em `config/sanctum.php`.

**Nota:** Mobile apps usam apenas `Authorization: Bearer {token}` - nao precisam de cookies/CSRF.

---

## 4. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BaseController.php
│   │       └── AuthController.php
│   ├── Requests/
│   │   └── Auth/
│   │       ├── GoogleAuthRequest.php
│   │       ├── UpdatePasswordRequest.php
│   │       └── UpdateProfileRequest.php
│   └── Resources/
│       └── UserResource.php
├── Models/
│   └── User.php
└── Services/
    └── Auth/
        └── GoogleAuthService.php

database/
└── migrations/
    └── 0001_01_01_000000_create_users_table.php

routes/
└── api.php

tests/
└── Feature/
    └── Auth/
        ├── GoogleAuthTest.php
        ├── LogoutTest.php
        ├── PasswordUpdateTest.php
        └── ProfileUpdateTest.php
```

---

## 5. Migration e Model

### 5.1 Migration: users

**Arquivo:** `database/migrations/core/2025_01_01_000001_create_users_table.php`

**Importante:** Esta migration ja foi executada no banco compartilhado. Ela esta aqui como **referencia** e sera copiada do `datagrana-web`. Quando rodar `php artisan migrate`, nao sera executada novamente (tabela `migrations` ja registra).

Implementado em `database/migrations/core/2025_01_01_000001_create_users_table.php`.

**Nota:** Copie esta migration exatamente como esta no `datagrana-web` para manter compatibilidade.

### 5.2 Model: User

**Arquivo:** `app/Models/User.php`

Implementado em `app/Models/User.php`.

### 5.3 Factory: UserFactory

**Arquivo:** `database/factories/UserFactory.php`

Implementado em `database/factories/UserFactory.php`.

---

## 6. Service de Autenticacao

### 6.1 GoogleAuthService

**Arquivo:** `app/Services/Auth/GoogleAuthService.php`

Implementado em `app/Services/Auth/GoogleAuthService.php`.

---

## 7. Controller e Requests

### 7.1 BaseController

**Arquivo:** `app/Http/Controllers/Api/BaseController.php`

Implementado em `app/Http/Controllers/Api/BaseController.php`.

### 7.2 GoogleAuthRequest

**Arquivo:** `app/Http/Requests/Auth/GoogleAuthRequest.php`

Implementado em `app/Http/Requests/Auth/GoogleAuthRequest.php`.

### 7.3 UpdateProfileRequest

**Arquivo:** `app/Http/Requests/Auth/UpdateProfileRequest.php`

Implementado em `app/Http/Requests/Auth/UpdateProfileRequest.php`.

### 7.4 UpdatePasswordRequest

**Arquivo:** `app/Http/Requests/Auth/UpdatePasswordRequest.php`

Implementado em `app/Http/Requests/Auth/UpdatePasswordRequest.php`.

### 7.5 AuthController

**Arquivo:** `app/Http/Controllers/Api/AuthController.php`

Implementado em `app/Http/Controllers/Api/AuthController.php`.

---

## 8. Resource

### 8.1 UserResource

**Arquivo:** `app/Http/Resources/UserResource.php`

Implementado em `app/Http/Resources/UserResource.php`.

---

## 9. Rotas

### 9.1 Arquivo de Rotas API

**Arquivo:** `routes/api.php`

Implementado em `routes/api.php`.

---

## 10. Casos de Teste

### 10.1 Teste Base (TestCase)

**Arquivo:** `tests/TestCase.php`

Implementado em `tests/TestCase.php`.

### 10.2 GoogleAuthTest

**Arquivo:** `tests/Feature/Auth/GoogleAuthTest.php`

Implementado em `tests/Feature/Auth/GoogleAuthTest.php`.

### 10.3 MeEndpointTest

**Arquivo:** `tests/Feature/Auth/MeEndpointTest.php`

Implementado em `tests/Feature/Auth/MeEndpointTest.php`.

### 10.4 LogoutTest

**Arquivo:** `tests/Feature/Auth/LogoutTest.php`

Implementado em `tests/Feature/Auth/LogoutTest.php`.

### 10.5 ProfileUpdateTest

**Arquivo:** `tests/Feature/Auth/ProfileUpdateTest.php`

Implementado em `tests/Feature/Auth/ProfileUpdateTest.php`.

### 10.6 PasswordUpdateTest

**Arquivo:** `tests/Feature/Auth/PasswordUpdateTest.php`

Implementado em `tests/Feature/Auth/PasswordUpdateTest.php`.

### 10.7 HealthCheckTest

**Arquivo:** `tests/Feature/HealthCheckTest.php`

Implementado em `tests/Feature/HealthCheckTest.php`.

### 10.6 Rodar Testes

```bash
# Rodar todos os testes
php artisan test

# Rodar apenas testes de auth
php artisan test --filter=Auth

# Rodar com coverage
php artisan test --coverage

# Rodar teste especifico
php artisan test --filter=test_can_login_with_valid_google_token
```

---

## 11. Checklist de Implementacao

### 11.1 Setup

- [ ] Verificar instalacao do `google/apiclient` (instalar se necessario)
- [x] Configurar `.env` com credenciais Google OAuth
- [x] Verificar `config/services.php` (adicionar Google)
- [x] Revisar `config/cors.php` (manter completo)
- [x] Revisar `config/sanctum.php` (manter completo)

### 11.2 Database

- [x] Copiar migration `users` do datagrana-web (se ainda nao copiada)
- [x] Verificar com `php artisan migrate` (nao deve criar nada novo)
- [x] Criar `UserFactory` (duplicar do datagrana-web se existir)

### 11.3 Backend

- [x] Criar `BaseController`
- [x] Criar `GoogleAuthService`
- [x] Criar `GoogleAuthRequest`
- [x] Criar `LoginRequest`
- [x] Criar `UpdateProfileRequest`
- [x] Criar `UpdatePasswordRequest`
- [x] Criar `AuthController`
- [x] Criar `UserResource`
- [x] Configurar rotas em `routes/api.php`

### 11.4 Testes

- [x] Criar `GoogleAuthTest`
- [x] Criar `LoginTest`
- [x] Criar `MeEndpointTest`
- [x] Criar `LogoutTest`
- [x] Criar `ProfileUpdateTest`
- [x] Criar `PasswordUpdateTest`
- [x] Criar `HealthCheckTest`
- [x] Rodar `php artisan test` - todos passando

### 11.5 Validacao Final

- [x] Testar endpoint `/api/health`
- [x] Testar login com token Google real (Postman/Insomnia)
- [x] Testar login com email e senha
- [x] Testar `/api/auth/me` com Bearer token
- [x] Testar `/api/auth/profile` com Bearer token
- [x] Testar `/api/auth/password` com Bearer token
- [x] Testar `/api/auth/logout`
- [x] Testar `/api/auth/logout-all`

---

## Endpoints da V1

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/health` | Nao | Health check |
| POST | `/api/auth/login` | Nao | Login com email e senha |
| POST | `/api/auth/google` | Nao | Login com Google |
| GET | `/api/auth/me` | Sim | Dados do usuario |
| GET | `/api/auth/profile` | Sim | Perfil do usuario |
| PATCH | `/api/auth/profile` | Sim | Atualizar perfil |
| PUT | `/api/auth/password` | Sim | Atualizar senha |
| POST | `/api/auth/logout` | Sim | Logout device atual |
| POST | `/api/auth/logout-all` | Sim | Logout todos devices |

---

## Proxima Fase

Apos completar a V1, prosseguir para:
- **V2 - Core**: Banks e Accounts (contas em corretoras)
