# Tests - Registro

Este arquivo registra cada teste criado, seu objetivo e cenarios cobertos.

---

## Resumo de Cobertura

| Modulo | Arquivos | Metodos | Status |
|--------|----------|---------|--------|
| Auth (V1) | 6 | 23 | ✅ Completo |
| Core (V2) | 6 | 22 | ✅ Completo |
| Companies (V3) | 3 | 9 | ✅ Completo |
| Consolidated (V4) | 6 | 18 | ✅ Completo |
| Portfolio (V5) | 8 | 24 | ✅ Completo |
| Crossing (V6) | 2 | 17 | ✅ Completo |
| Subscription (V7) | 5 | 38 | ✅ Completo |
| Health | 1 | 1 | ✅ Completo |
| **Total** | **37** | **152** | |

---

## Auth (V1)

### `tests/Feature/Auth/LoginTest.php`
Valida login por email/senha.

| Metodo | Cenario |
|--------|---------|
| `test_can_login_with_valid_credentials` | Login com credenciais validas retorna token |
| `test_cannot_login_with_invalid_password` | Senha incorreta retorna 401 |
| `test_cannot_login_when_user_is_inactive` | Usuario inativo retorna 403 |
| `test_cannot_login_without_email` | Email obrigatorio (422) |
| `test_cannot_login_without_password` | Senha obrigatoria (422) |
| `test_revokes_previous_tokens_on_login` | Tokens antigos sao revogados |

### `tests/Feature/Auth/GoogleAuthTest.php`
Cobre login via Google OAuth.

| Metodo | Cenario |
|--------|---------|
| `test_can_login_with_valid_google_token` | Token Google valido retorna token Sanctum |
| `test_cannot_login_with_invalid_google_token` | Token invalido retorna 401 |
| `test_cannot_login_when_google_user_is_inactive` | Usuario Google inativo retorna 403 |
| `test_creates_user_on_first_google_login` | Primeiro login cria usuario automaticamente |
| `test_revokes_previous_tokens_on_google_login` | Tokens antigos sao revogados |

### `tests/Feature/Auth/MeEndpointTest.php`
Valida `GET /api/auth/me`.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_authenticated_user` | Token valido retorna dados do usuario |
| `test_cannot_get_user_without_token` | Sem token retorna 401 |
| `test_cannot_get_user_with_invalid_token` | Token invalido retorna 401 |
| `test_cannot_get_user_with_revoked_token` | Token revogado retorna 401 |

### `tests/Feature/Auth/LogoutTest.php`
Valida logout e revogacao de tokens.

| Metodo | Cenario |
|--------|---------|
| `test_can_logout` | `POST /api/auth/logout` revoga token atual |
| `test_can_logout_all_devices` | `POST /api/auth/logout-all` revoga todos os tokens |

### `tests/Feature/Auth/ProfileUpdateTest.php`
Valida atualizacao de perfil.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_profile` | `GET /api/auth/profile` retorna dados |
| `test_can_update_name` | Atualizacao de nome |
| `test_can_update_email` | Atualizacao de email |
| `test_email_must_be_unique` | Email duplicado retorna 422 |

### `tests/Feature/Auth/PasswordUpdateTest.php`
Valida troca de senha.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_password` | Troca de senha com senha atual valida |
| `test_cannot_update_with_wrong_current_password` | Senha atual incorreta retorna 422 |
| `test_requires_authentication` | Sem auth retorna 401 |

---

## Core (V2)

### `tests/Feature/Bank/BankListTest.php`
Valida listagem de bancos.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_active_banks` | Retorna apenas bancos ativos |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Account/AccountIndexTest.php`
Valida listagem de contas.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_accounts` | Lista apenas contas do usuario |
| `test_orders_by_default_first` | Conta default aparece primeiro |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Account/AccountStoreTest.php`
Valida criacao de contas.

| Metodo | Cenario |
|--------|---------|
| `test_can_create_account` | Criacao com dados validos |
| `test_first_account_is_automatically_default` | Primeira conta eh default automatico |
| `test_setting_new_default_removes_old_default` | Nova default remove antiga |
| `test_cannot_create_duplicate_account_number` | Numero duplicado retorna 422 |
| `test_can_create_account_without_bank` | Banco eh opcional |
| `test_cannot_create_account_with_inactive_bank` | Banco inativo retorna 422 |
| `test_account_number_is_required` | Numero obrigatorio (422) |

### `tests/Feature/Account/AccountShowTest.php`
Valida detalhes da conta.

| Metodo | Cenario |
|--------|---------|
| `test_can_view_own_account` | Acesso a conta propria |
| `test_cannot_view_other_user_account` | Conta de terceiro retorna 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Account/AccountUpdateTest.php`
Valida atualizacao de conta.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_own_account` | Atualizacao com dados validos |
| `test_can_change_default` | Troca de conta default |
| `test_cannot_update_other_user_account` | Conta de terceiro retorna 403 |
| `test_cannot_duplicate_account_number` | Numero duplicado retorna 422 |

### `tests/Feature/Account/AccountDestroyTest.php`
Valida remocao de conta.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_own_account` | Remocao de conta propria |
| `test_cannot_delete_other_user_account` | Conta de terceiro retorna 403 |
| `test_deleting_default_assigns_new_default` | Remove default reassina para outra |
| `test_can_delete_last_account` | Pode remover ultima conta |

---

## Companies (V3)

### `tests/Feature/Asset/AssetCategoriesTest.php`
Valida listagem de categorias.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_active_categories` | Retorna categorias ativas ordenadas |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Asset/AssetSearchTest.php`
Valida busca de ativos.

| Metodo | Cenario |
|--------|---------|
| `test_can_search_by_ticker` | Busca por codigo do ticker |
| `test_can_search_by_name` | Busca por nome da empresa |
| `test_can_filter_by_category` | Filtro por categoria |
| `test_respects_limit_parameter` | Limite de resultados |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Asset/AssetShowTest.php`
Valida detalhes do ativo.

| Metodo | Cenario |
|--------|---------|
| `test_can_view_asset_details` | Retorna dados completos do ativo |
| `test_returns_404_for_nonexistent_asset` | Ativo inexistente retorna 404 |
| `test_requires_authentication` | Sem auth retorna 401 |

---

## Consolidated (V4)

### `tests/Feature/Consolidated/ConsolidatedIndexTest.php`
Valida listagem de posicoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_positions` | Lista apenas posicoes do usuario |
| `test_can_filter_by_account` | Filtro por conta |
| `test_can_filter_by_closed_status` | Filtro por status fechado/aberto |
| `test_cannot_list_positions_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedShowTest.php`
Valida detalhes da posicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_view_own_position` | Acesso a posicao propria |
| `test_cannot_view_other_user_position` | Posicao de terceiro retorna 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`
Valida resumo das posicoes.

| Metodo | Cenario |
|--------|---------|
| `test_returns_summary_of_open_positions` | Resumo exclui posicoes fechadas |
| `test_calculates_totals_correctly` | Totais calculados corretamente |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedTransactionStoreTest.php`
Valida criacao de transacoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_create_company_transactions` | Compra e venda de acoes |
| `test_returns_error_when_selling_more_than_available` | Venda sem saldo retorna 422 |
| `test_marks_position_as_closed_on_full_sell` | Venda total fecha posicao |
| `test_can_create_treasure_transactions` | Compra e venda de tesouros |

### `tests/Feature/Consolidated/ConsolidatedTransactionUpdateTest.php`
Valida atualizacao de transacoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_own_transaction` | Atualizacao recalcula consolidado |
| `test_cannot_update_other_user_transaction` | Transacao de terceiro retorna 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedTransactionDestroyTest.php`
Valida remocao de transacoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_own_transaction` | Remocao recalcula consolidado |
| `test_removes_consolidated_when_no_transactions` | Remove consolidado se zerou |
| `test_cannot_delete_other_user_transaction` | Transacao de terceiro retorna 403 |

---

## Portfolio (V5)

### `tests/Feature/Portfolio/PortfolioIndexTest.php`
Valida listagem de portfolios.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_portfolios` | Lista apenas portfolios do usuario |
| `test_can_filter_by_name` | Filtro por nome |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Portfolio/PortfolioStoreTest.php`
Valida criacao de portfolio.

| Metodo | Cenario |
|--------|---------|
| `test_can_create_portfolio` | Criacao com dados validos |
| `test_name_is_required` | Nome obrigatorio (422) |
| `test_values_are_required` | Valores obrigatorios (422) |
| `test_values_cannot_be_negative` | Valores negativos retornam 422 |

### `tests/Feature/Portfolio/PortfolioUpdateTest.php`
Valida atualizacao de portfolio.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_own_portfolio` | Atualizacao com dados validos |
| `test_cannot_update_other_user_portfolio` | Portfolio de terceiro retorna 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Portfolio/PortfolioDestroyTest.php`
Valida remocao de portfolio.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_own_portfolio` | Soft delete de portfolio |
| `test_cannot_delete_other_user_portfolio` | Portfolio de terceiro retorna 403 |
| `test_cannot_delete_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Portfolio/CompositionStoreTest.php`
Valida adicao de composicoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_add_company_composition` | Adicao de acao a carteira |
| `test_can_add_treasure_composition` | Adicao de tesouro a carteira |
| `test_validates_percentage` | Percentual deve ser >= 0 |
| `test_cannot_add_to_other_user_portfolio` | Portfolio de terceiro retorna 403 |

### `tests/Feature/Portfolio/CompositionUpdateTest.php`
Valida atualizacao de composicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_percentage` | Atualizacao de percentual |
| `test_cannot_update_other_user_composition` | Composicao de terceiro retorna 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Portfolio/CompositionUpdateBatchTest.php`
Valida atualizacao em lote.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_multiple_compositions` | Atualizacao em lote |
| `test_cannot_update_other_user_compositions` | Composicoes de terceiro retornam 403 |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Portfolio/CompositionDestroyTest.php`
Valida remocao de composicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_remove_composition` | Remocao simples |
| `test_can_save_to_history_on_remove` | Remocao com historico |
| `test_cannot_remove_other_user_composition` | Composicao de terceiro retorna 403 |
| `test_cannot_remove_without_authentication` | Sem auth retorna 401 |

---

## Crossing (V6)

### `tests/Feature/Portfolio/CrossingTest.php`
Valida dados de crossing.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_crossing_data` | Retorna estrutura completa de crossing |
| `test_masks_crossing_when_full_access_is_disabled` | Mascara dados quando plano nao permite |
| `test_calculates_to_buy_quantity_correctly` | Calculo de quantidade a comprar |
| `test_identifies_not_positioned_assets` | Status `not_positioned` para ativos sem posicao |
| `test_identifies_unwind_positions` | Status `unwind_position` para ativos no historico |
| `test_returns_null_to_buy_when_no_price` | Retorna null quando nao ha preco |
| `test_crossing_includes_treasures` | Inclui tesouros no crossing |
| `test_cannot_get_crossing_for_other_user_portfolio` | Portfolio de terceiro retorna 403 |
| `test_cannot_get_crossing_without_authentication` | Sem auth retorna 401 |

### `tests/Unit/Helpers/PortfolioHelperTest.php`
Valida calculos do helper.

| Metodo | Cenario |
|--------|---------|
| `test_calculates_to_buy_quantity` | Calculo basico de quantidade |
| `test_returns_zero_when_already_reached_target` | Retorna 0 quando ja atingiu meta |
| `test_returns_null_when_no_price` | Retorna null sem preco |
| `test_returns_null_when_price_is_zero` | Retorna null com preco zero |
| `test_returns_zero_when_percentage_is_zero` | Retorna 0 com percentual zero |
| `test_returns_dash_when_deleted` | Retorna `-` para ativos deletados |
| `test_formats_quantity_correctly` | Formatacao de quantidade |

---

## Subscription Limits (V7)

### `tests/Feature/Subscription/SubscriptionLimitsTest.php`
Valida limites de assinatura.

| Metodo | Cenario |
|--------|---------|
| `test_user_receives_free_subscription_on_create` | Usuario novo recebe plano free |
| `test_free_plan_blocks_second_account_creation` | Plano free bloqueia 2a conta |
| `test_free_plan_has_limited_crossing_access` | Plano free nao tem crossing completo |
| `test_composition_limits_apply_per_portfolio` | Limites de composicao por portfolio |

### `tests/Feature/Subscription/RevenueCatWebhookTest.php`
Valida processamento de webhooks do RevenueCat.

| Metodo | Cenario |
|--------|---------|
| `test_initial_purchase_creates_paid_subscription` | INITIAL_PURCHASE cria assinatura paga |
| `test_renewal_updates_renews_at_and_increments_renewal_count` | RENEWAL atualiza renews_at e renewal_count |
| `test_cancellation_in_trial_cuts_access_immediately` | CANCELLATION em trial corta acesso imediato |
| `test_cancellation_outside_trial_maintains_access_until_ends_at` | CANCELLATION fora de trial mantem acesso |
| `test_expiration_marks_subscription_as_expired` | EXPIRATION marca assinatura como expired |
| `test_invalid_auth_header_returns_error` | Auth header invalido retorna erro |
| `test_duplicate_event_is_ignored` | Evento duplicado (mesmo event_id) e ignorado |
| `test_webhook_logs_are_created` | Logs de webhook sao criados corretamente |

### `tests/Feature/Subscription/SubscriptionPlanTest.php`
Valida endpoints de planos de assinatura.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_active_subscription_plans` | Lista apenas planos ativos |
| `test_subscription_plans_are_ordered_by_display_order` | Planos ordenados por display_order |
| `test_plans_include_configs` | Planos incluem configs relacionadas |
| `test_can_show_subscription_plan` | Detalhes de plano especifico |
| `test_returns_404_for_nonexistent_plan` | Plano inexistente retorna 404 |
| `test_cannot_list_plans_without_authentication` | Sem auth retorna 401 |
| `test_cannot_show_plan_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Subscription/UserSubscriptionTest.php`
Valida endpoints de assinatura do usuario.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_current_subscription` | Retorna assinatura ativa do usuario |
| `test_current_subscription_creates_free_if_none_exists` | Cria free se nenhuma ativa |
| `test_has_had_paid_plan_is_true_when_user_had_paid_subscription` | Flag has_had_paid_plan true com historico |
| `test_has_had_paid_plan_is_false_when_user_never_had_paid_subscription` | Flag false sem historico pago |
| `test_can_get_subscription_history` | Historico de assinaturas |
| `test_cannot_get_current_subscription_without_authentication` | Sem auth retorna 401 |
| `test_cannot_get_history_without_authentication` | Sem auth retorna 401 |
| `test_returns_only_own_subscriptions_in_history` | Apenas assinaturas proprias |

### `tests/Feature/Subscription/SubscriptionMiddlewareTest.php`
Valida middleware de limites de assinatura.

| Metodo | Cenario |
|--------|---------|
| `test_middleware_blocks_portfolio_creation_when_limit_reached` | Bloqueia criacao de portfolio no limite |
| `test_middleware_allows_portfolio_creation_when_under_limit` | Permite criacao abaixo do limite |
| `test_middleware_blocks_account_creation_when_limit_reached` | Bloqueia criacao de conta no limite |
| `test_middleware_skips_when_enforce_limits_is_disabled` | Ignora quando enforce_limits=false |
| `test_middleware_blocks_composition_creation_when_limit_reached` | Bloqueia criacao de composicao no limite |

### `tests/Feature/Subscription/SubscriptionUpgradeDowngradeTest.php`
Valida upgrade e downgrade de planos.

| Metodo | Cenario |
|--------|---------|
| `test_upgrade_updates_limits_immediately` | Upgrade atualiza limites imediatamente |
| `test_downgrade_schedules_pending_plan` | Downgrade agenda plano pendente |
| `test_product_change_downgrade_schedules_pending_plan` | PRODUCT_CHANGE agenda downgrade |
| `test_limits_apply_based_on_active_subscription` | Limites baseados em assinatura ativa |
| `test_upgrade_cancels_previous_active_subscription` | Upgrade cancela assinatura anterior |

---

## Health

### `tests/Feature/HealthCheckTest.php`
Valida endpoint de health check.

| Metodo | Cenario |
|--------|---------|
| `test_health_endpoint_returns_success` | `GET /api/health` retorna 200 |

---

## Gaps de Cobertura (V7 - Subscription)

Os seguintes cenarios ainda nao possuem testes automatizados:

### Cenarios faltantes

| Categoria | Cenario | Prioridade |
|-----------|---------|------------|
| **Limites** | `is_locked` em AccountResource | Baixa |
| **Limites** | `is_locked` em PortfolioResource | Baixa |
| **Limites** | `is_locked` em CompositionResource | Baixa |
| **Webhooks** | BILLING_ISSUE loga warning | Baixa |
| **Webhooks** | PRODUCT_CHANGE upgrade aplica imediatamente | Media |

---

## Configuracao de Ambiente de Testes

### `.env.testing`

Usa SQLite em arquivo para evitar uso do banco real:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/testing.sqlite
```

### Executar testes

```bash
# Todos os testes
php artisan test

# Testes de um modulo
php artisan test --filter=Auth
php artisan test --filter=Consolidated

# Teste especifico
php artisan test --filter=LoginTest
php artisan test --filter=test_can_login_with_valid_credentials
```

### Helper methods (TestCase)

O `Tests\TestCase` base fornece helpers:

- `createAuthenticatedUser()` - Cria usuario e retorna `['user' => User, 'token' => string]`
- `authHeaders($token)` - Retorna headers com `Authorization: Bearer $token`

---

*Ultima atualizacao: Fevereiro 2026 - Tests V7 Subscription completos*
