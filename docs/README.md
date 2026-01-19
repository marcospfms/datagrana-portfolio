# DataGrana Portfolio - Documentacao

> Sistema de gestao de carteiras de investimento em Renda Variavel.

---

## Roadmaps de Implementacao

Os roadmaps estao organizados por fase/modulo, seguindo a ordem logica de dependencias:

| Fase | Arquivo | Descricao | Dependencia |
|------|---------|-----------|-------------|
| V1 | [roadmap-v1-auth.md](./roadmap-v1-auth.md) | Setup + Autenticacao Google OAuth | - |
| V2 | [roadmap-v2-core.md](./roadmap-v2-core.md) | Banks + Accounts | V1 |
| V3 | [roadmap-v3-companies.md](./roadmap-v3-companies.md) | Categories, Companies, Tickers | V2 |
| V4 | [roadmap-v4-consolidated.md](./roadmap-v4-consolidated.md) | Posicoes reais (compras) | V3 |
| V5 | [roadmap-v5-portfolio.md](./roadmap-v5-portfolio.md) | Carteiras + Composicoes | V4 |
| V6 | [roadmap-v6-crossing.md](./roadmap-v6-crossing.md) | Comparacao ideal vs real | V5 |

---

## Visao Geral

### Fluxo do Usuario

```
1. Login com Google (cria usuario se nao existe)
2. Criar Account (conta na corretora)
3. Cadastrar compras de ativos (posicoes consolidadas)
4. Criar Portfolio com ativos e percentuais
5. Visualizar comparacao (Crossing)
```

### Stack Tecnologico

**Backend:**
- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL/MariaDB

**Frontend (consumidor):**
- React Native + Expo
- TypeScript

---

## Estrutura do Banco de Dados

```
users
├── accounts
│   └── consolidated
│       └── company_tickers
│           └── companies
│               └── company_category
└── portfolios
    ├── portfolio_compositions
    │   └── company_tickers
    └── portfolio_composition_histories
        └── company_tickers

banks (dados de apoio)
```

---

## Endpoints da API

### Autenticacao (V1)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| POST | `/api/auth/google` | Nao | Login Google |
| GET | `/api/auth/me` | Sim | Dados do usuario |
| POST | `/api/auth/logout` | Sim | Logout |

### Core (V2)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/banks` | Sim | Lista corretoras |
| GET | `/api/accounts` | Sim | Lista contas |
| POST | `/api/accounts` | Sim | Cria conta |
| GET | `/api/accounts/{id}` | Sim | Detalhes |
| PUT | `/api/accounts/{id}` | Sim | Atualiza |
| DELETE | `/api/accounts/{id}` | Sim | Remove |

### Assets (V3)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/assets/categories` | Sim | Lista categorias |
| GET | `/api/assets/search` | Sim | Busca ativos |
| GET | `/api/assets/{id}` | Sim | Detalhes |

### Consolidated (V4)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/consolidated` | Sim | Lista posicoes |
| POST | `/api/consolidated` | Sim | Registra posicao |
| GET | `/api/consolidated/{id}` | Sim | Detalhes |
| PUT | `/api/consolidated/{id}` | Sim | Atualiza |
| DELETE | `/api/consolidated/{id}` | Sim | Remove |
| GET | `/api/consolidated/summary` | Sim | Resumo |

### Portfolio (V5)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios` | Sim | Lista portfolios |
| POST | `/api/portfolios` | Sim | Cria portfolio |
| GET | `/api/portfolios/{id}` | Sim | Detalhes |
| PUT | `/api/portfolios/{id}` | Sim | Atualiza |
| DELETE | `/api/portfolios/{id}` | Sim | Remove |
| POST | `/api/portfolios/{id}/compositions` | Sim | Adiciona ativos |
| PUT | `/api/compositions/{id}` | Sim | Atualiza % |
| DELETE | `/api/compositions/{id}` | Sim | Remove ativo |

### Crossing (V6)
| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios/{id}/crossing` | Sim | Comparacao |

---

## Padroes de Projeto

Os padroes estao documentados em [patterns/](./patterns/):

- [controllers.md](./patterns/controllers.md) - BaseController, sendResponse/sendError
- [models.md](./patterns/models.md) - Convencoes de nomenclatura
- [resources.md](./patterns/resources.md) - API Resources
- [services.md](./patterns/services.md) - Camada de servicos
- [tests.md](./patterns/tests.md) - Testes automatizados

---

## Comandos Uteis

```bash
# Criar projeto
composer create-project laravel/laravel datagrana-portfolio

# Instalar dependencias
composer require laravel/sanctum google/apiclient

# Rodar migrations
php artisan migrate

# Rodar seeders
php artisan db:seed

# Rodar testes
php artisan test

# Rodar servidor
php artisan serve
```

---

## Documentacao Legada

O arquivo [roadmap-app.md](./roadmap-app.md) contem a documentacao completa consolidada (versao anterior). Use os arquivos separados por fase para implementacao.
