# Roadmap V6 - Crossing (Comparacao Ideal vs Real)

> Comparacao entre portfolio ideal e posicoes reais consolidadas.

**Dependencia:** V5 completa. **Migrations copiadas** do `datagrana-web` (banco compartilhado).

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Helper](#4-helper)
5. [Service](#5-service)
6. [Atualizar Controller](#6-atualizar-controller)
7. [Rotas](#7-rotas)
8. [Casos de Teste](#8-casos-de-teste)
9. [Checklist de Implementacao](#9-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a funcionalidade de **Crossing** (cruzamento):

- Comparar composicao ideal vs posicoes reais
- Calcular quanto comprar de cada ativo
- Identificar ativos a desmontar (no historico mas com posicao)
- Ordenar por categoria e ticker

**Entregaveis:**
- `PortfolioHelper` com calculos
- `CrossingService` com logica de comparacao
- Endpoint `/api/portfolios/{id}/crossing`
- Testes automatizados

---

## Regras de Negocio

### Histórico de Composição
- Usa **soft delete** (`deleted_at`) para marcar remoções
- Mantém histórico completo de todas as versões
- Ao remover ativo da carteira, preenche `deleted_at`

### Status do Crossing
- `positioned`: ativo na composicao e com posicao consolidada
- `not_positioned`: ativo na composicao sem posicao
- `unwind_position`: ativo fora da composicao, mas presente no historico e ainda com posicao

### Precos Ausentes (last_price)
- Quando nao ha cotacao disponivel: retorna `null`
- UI deve mostrar icone de duvida

### Cálculo de Totais
- `total_current` = soma de `balance`
- `total_invested` = soma de `total_purchased`
- `total_profit` = `total_current - total_invested`

## Regras de Interface (App)

- Agrupar itens por `category` mantendo a ordem enviada pela API.
- Status e cores:
  - `positioned`: verde
  - `not_positioned`: amarelo
  - `unwind_position`: vermelho
- Estatisticas globais:
  - `totalIdealPercentage`: soma de `ideal_percentage` apenas de itens != `unwind_position`.
  - `totalCurrentValue`: soma de `balance`.
  - `positionedAssets`, `notPositionedAssets`, `unwindAssets`: contagem por status.
  - `totalInvested`: soma de `total_purchased`.
  - `totalToBuyQuantity`: soma de `to_buy_quantity` quando numerico.
  - `avgProfitPercentage`: media de `profit_percentage` dos itens com valor != 0.
  - `profitableAssets`: `profit_percentage > 0`.
  - `lossAssets`: `profit_percentage < 0`.
  - `perfectlyPositioned`: progress entre 95% e 105%.
  - `totalProfit`: soma de `profit`.
  - `resultValue`: `totalCurrentValue - totalInvested`.
- Progress percent por ativo:
  - `meta = portfolio.target_value * (ideal_percentage / 100)`
  - `progress = (balance / meta) * 100` (se `ideal_percentage == 0`, retorna 0).
- Exibicao:
  - `to_buy_quantity === null`: mostrar icone de duvida.
  - `to_buy_quantity > 0`: mostrar texto `to_buy_quantity_formatted` e icone de carrinho.
  - `to_buy_quantity === '-'`: mostrar apenas `-`.
  - Para itens posicionados, exibir `total_purchased`, `current_quantity`, `average_purchase_price`, `dividend_received`.
  - Para itens nao posicionados, exibir `ideal_percentage` e `to_buy_quantity_formatted`.

---

## 2. Dependencias

**Requer:** V5 (Portfolio) completa

**Tabelas necessarias:**
- `portfolios`
- `compositions`
- `composition_histories`
- `consolidated`
- `company_tickers`
- `treasures`

---

## 3. Estrutura de Arquivos

```
app/
├── Helpers/
│   └── PortfolioHelper.php
├── Services/
│   └── Portfolio/
│       └── CrossingService.php
└── Http/
    └── Controllers/
        └── Api/
            └── PortfolioController.php (atualizar)

tests/
└── Feature/
    └── Portfolio/
        └── CrossingTest.php
tests/
└── Unit/
    └── Helpers/
        └── PortfolioHelperTest.php
```

---

## 4. Helper

Implementado em `app/Helpers/PortfolioHelper.php`.

---

## 5. Service

Implementado em `app/Services/Portfolio/CrossingService.php`.

---

## 6. Atualizar Controller

Metodo `crossing` implementado em `app/Http/Controllers/Api/PortfolioController.php`.

---

## 7. Rotas

Rota adicionada em `routes/api.php`.

---

## 8. Casos de Teste

- `tests/Feature/Portfolio/CrossingTest.php`
- `tests/Unit/Helpers/PortfolioHelperTest.php`

---

## 9. Checklist de Implementacao

### 9.1 Backend

- [x] Criar `app/Helpers/PortfolioHelper.php`
- [x] Criar `app/Services/Portfolio/CrossingService.php`
- [x] Atualizar `PortfolioController` com metodo `crossing`
- [x] Configurar rota `/api/portfolios/{id}/crossing`

### 9.2 Testes

- [x] Criar `tests/Feature/Portfolio/CrossingTest.php`
- [x] Criar `tests/Unit/Helpers/PortfolioHelperTest.php`
- [ ] Rodar `php artisan test` - todos passando

### 9.3 Validacao Final

- [ ] Testar `GET /api/portfolios/{id}/crossing` com posicoes
- [ ] Testar com ativos sem posicao (not_positioned)
- [ ] Testar com ativos no historico (unwind_position)
- [ ] Verificar calculos de to_buy_quantity
- [ ] Verificar ordenacao por categoria/ticker
- [ ] Verificar totais calculados
- [ ] Testar casos com `last_price = null`

---

## Endpoint da V6

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios/{id}/crossing` | Sim | Comparacao ideal vs real |

---

## Resposta do Endpoint

```json
{
  "success": true,
  "data": {
    "portfolio": {
      "id": 1,
      "name": "Meu Portfolio",
      "target_value": "10000.00",
      "month_value": "1000.00",
      "total_percentage": "100.00"
    },
    "crossing": [
      {
        "ticker": "PETR4",
        "name": "Petrobras",
        "category": "Acoes",
        "ideal_percentage": 25.0,
        "total_purchased": 1500.0,
        "balance": 1750.0,
        "profit": 250.0,
        "profit_percentage": 16.67,
        "last_price": 35.0,
        "to_buy_quantity": 21,
        "to_buy_quantity_formatted": "21 cotas",
        "status": "positioned"
      }
    ]
  }
}
```

---

## Projeto Concluido!

Com a V6 completa, o projeto **DataGrana Portfolio** esta pronto com todas as funcionalidades:

1. **V1 - Auth**: Login Google OAuth + Sanctum
2. **V2 - Core**: Banks + Accounts
3. **V3 - Companies**: Categorias + Empresas + Tickers
4. **V4 - Consolidated**: Posicoes reais (compras)
5. **V5 - Portfolio**: Carteiras ideais + Composicoes
6. **V6 - Crossing**: Comparacao ideal vs real

**Fluxo completo do usuario:**
1. Login com Google
2. Criar conta na corretora
3. Registrar compras de ativos
4. Criar portfolio com alocacoes
5. Visualizar crossing (quanto comprar de cada ativo)
