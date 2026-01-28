# Tests - Registro

Este arquivo registra cada teste criado e seu objetivo.

## Auth (V1)

- `tests/Feature/Auth/LoginTest.php`: valida login por email/senha, usuario inativo, validacoes e revogacao de tokens antigos.
- `tests/Feature/Auth/GoogleAuthTest.php`: cobre login via Google (token valido/invalido), usuario inativo, criacao de usuario e revogacao de tokens antigos.
- `tests/Feature/Auth/MeEndpointTest.php`: valida `GET /api/auth/me` com token valido e bloqueia acessos sem token/invalido/revogado.
- `tests/Feature/Auth/LogoutTest.php`: valida `POST /api/auth/logout` e `POST /api/auth/logout-all`, incluindo revogacao de tokens.
- `tests/Feature/Auth/ProfileUpdateTest.php`: valida `GET /api/auth/profile`, atualizacao de nome/email e validacoes basicas.
- `tests/Feature/Auth/PasswordUpdateTest.php`: valida `PUT /api/auth/password`, incluindo senha atual invalida e auth obrigatoria.

## Core (V2)

- `tests/Feature/Bank/BankListTest.php`: valida listagem de bancos ativos e requer autenticacao.
- `tests/Feature/Account/AccountIndexTest.php`: valida listagem de contas do usuario e ordenacao por default.
- `tests/Feature/Account/AccountStoreTest.php`: valida criacao de contas, default automatico e validacoes.
- `tests/Feature/Account/AccountShowTest.php`: valida acesso a conta propria e bloqueio de terceiros.
- `tests/Feature/Account/AccountUpdateTest.php`: valida atualizacao, troca de default e validacao de duplicidade.
- `tests/Feature/Account/AccountDestroyTest.php`: valida remocao, bloqueio de terceiros e reassinacao de default.

## Companies (V3)

- `tests/Feature/Asset/AssetCategoriesTest.php`: valida listagem de categorias ativas e ordenacao.
- `tests/Feature/Asset/AssetSearchTest.php`: valida busca de ativos por ticker/nome, filtros e limit.
- `tests/Feature/Asset/AssetShowTest.php`: valida detalhe de ativo e respostas 404/401.

## Consolidated (V4)

- `tests/Feature/Consolidated/ConsolidatedIndexTest.php`: valida listagem de posicoes do usuario e filtros basicos.
- `tests/Feature/Consolidated/ConsolidatedShowTest.php`: valida acesso a posicao propria e bloqueio de terceiros.
- `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`: valida resumo das posicoes e exclusao de fechadas.
- `tests/Feature/Consolidated/ConsolidatedTransactionStoreTest.php`: valida criacao de transacoes e erro de venda sem saldo.
- `tests/Feature/Consolidated/ConsolidatedTransactionUpdateTest.php`: valida atualizacao de transacoes e recalc do consolidado.
- `tests/Feature/Consolidated/ConsolidatedTransactionDestroyTest.php`: valida remocao de transacoes e cleanup do consolidado.

## Portfolio (V5)

- `tests/Feature/Portfolio/PortfolioIndexTest.php`: valida listagem de portfolios e filtro por nome.
- `tests/Feature/Portfolio/PortfolioStoreTest.php`: valida criacao de portfolio e validacoes de campos.
- `tests/Feature/Portfolio/PortfolioUpdateTest.php`: valida atualizacao e bloqueio de terceiros.
- `tests/Feature/Portfolio/PortfolioDestroyTest.php`: valida remocao (soft delete) e bloqueio de terceiros.
- `tests/Feature/Portfolio/CompositionStoreTest.php`: valida adicao de composicoes para ativos e validacoes.
- `tests/Feature/Portfolio/CompositionUpdateTest.php`: valida atualizacao de porcentagem e bloqueio de terceiros.
- `tests/Feature/Portfolio/CompositionUpdateBatchTest.php`: valida atualizacao em lote e bloqueio de terceiros.
- `tests/Feature/Portfolio/CompositionDestroyTest.php`: valida remocao e registro de historico.

## Crossing (V6)

- `tests/Feature/Portfolio/CrossingTest.php`: valida dados de crossing, status, calculos de compra e mascara de dados quando o plano nao permite crossing completo.
- `tests/Unit/Helpers/PortfolioHelperTest.php`: valida calculos e formatacao do helper de crossing.

## Subscription Limits (V7)

- `tests/Feature/Subscription/SubscriptionLimitsTest.php`: valida assinatura gratuita automatica, limites do plano free e acesso limitado ao crossing.

## Health

- `tests/Feature/HealthCheckTest.php`: valida `GET /api/health` com resposta de sucesso.

## Configuracao de Ambiente de Testes

- `.env.testing`: usa SQLite em arquivo (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/testing.sqlite`) para evitar uso do banco real.
