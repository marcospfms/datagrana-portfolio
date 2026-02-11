# Tests - Registro

Este arquivo registra cada teste criado, seu objetivo e cenarios cobertos.

---

## Resumo de Cobertura

| Modulo | Arquivos | Metodos | Cobertura | Status |
|--------|----------|---------|-----------|--------|
| Auth (V1) | 6 | 30 | 100% | ✅ Completo |
| Core (V2) | 6 | 23 | 100% | ✅ Completo |
| Companies (V3) | 4 | 15 | 100% | ✅ Completo |
| Consolidated (V4) | 9 | 29 | 100% | ✅ Completo |
| Portfolio (V5) | 8 | 27 | 100% | ✅ Completo |
| Crossing (V6) | 2 | 17 | 100% | ✅ Completo |
| Subscription (V7) | 7 | 45 | 100% | ✅ Completo |
| Earnings (V8) | 7 | 25 | 100% | ✅ Completo |
| Health | 1 | 1 | 100% | ✅ Completo |
| **Total** | **50** | **224** | 100% | ✅ |

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
| `test_cannot_login_without_id_token` | Token obrigatorio (422) |
| `test_cannot_login_with_empty_id_token` | Token vazio retorna erro |
| `test_cannot_login_when_user_is_inactive` | Usuario Google inativo retorna 403 |
| `test_creates_new_user_on_first_login` | Primeiro login cria usuario automaticamente |
| `test_revokes_previous_tokens_on_login` | Tokens antigos sao revogados |

### `tests/Feature/Auth/MeEndpointTest.php`
Valida `GET /api/auth/me`.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_authenticated_user_data` | Token valido retorna dados do usuario |
| `test_cannot_get_user_data_without_token` | Sem token retorna 401 |
| `test_cannot_get_user_data_with_invalid_token` | Token invalido retorna 401 |
| `test_cannot_get_user_data_with_revoked_token` | Token revogado retorna 401 |

### `tests/Feature/Auth/LogoutTest.php`
Valida logout e revogacao de tokens.

| Metodo | Cenario |
|--------|---------|
| `test_can_logout_current_device` | `POST /api/auth/logout` revoga token atual |
| `test_can_logout_all_devices` | `POST /api/auth/logout-all` revoga todos os tokens |
| `test_cannot_logout_without_authentication` | Sem auth retorna 401 |
| `test_cannot_logout_all_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Auth/ProfileUpdateTest.php`
Valida atualizacao de perfil.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_profile` | `GET /api/auth/profile` retorna dados |
| `test_can_update_profile` | Atualizacao de nome e email |
| `test_cannot_update_profile_without_authentication` | Sem auth retorna 401 |
| `test_cannot_update_profile_with_invalid_email` | Email invalido retorna 422 |
| `test_cannot_update_profile_with_existing_email` | Email duplicado retorna 422 |
| `test_cannot_update_profile_when_user_is_google_account` | Usuario Google nao pode alterar perfil local |

### `tests/Feature/Auth/PasswordUpdateTest.php`
Valida troca de senha.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_password` | Troca de senha com senha atual valida |
| `test_cannot_update_password_with_invalid_current_password` | Senha atual incorreta retorna 422 |
| `test_cannot_update_password_without_authentication` | Sem auth retorna 401 |
| `test_cannot_update_password_when_user_is_google_account` | Usuario Google nao pode alterar senha |

---

## Core (V2)

### `tests/Feature/Bank/BankListTest.php`
Valida listagem de bancos.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_active_banks` | Retorna apenas bancos ativos |
| `test_cannot_list_banks_without_authentication` | Sem auth retorna 401 |
| `test_banks_are_ordered_by_name` | Ordenacao alfabetica |

### `tests/Feature/Account/AccountIndexTest.php`
Valida listagem de contas.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_accounts` | Lista apenas contas do usuario |
| `test_cannot_list_accounts_without_authentication` | Sem auth retorna 401 |
| `test_default_account_comes_first` | Conta default aparece primeiro |

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
| `test_returns_404_for_nonexistent_account` | Conta inexistente retorna 404 |

### `tests/Feature/Account/AccountUpdateTest.php`
Valida atualizacao de conta.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_own_account` | Atualizacao com dados validos |
| `test_cannot_update_other_user_account` | Conta de terceiro retorna 403 |
| `test_setting_default_removes_other_defaults` | Troca de conta default |
| `test_cannot_duplicate_account_number_on_update` | Numero duplicado retorna 422 |
| `test_cannot_update_account_outside_limit` | Limite de edicao do plano |

### `tests/Feature/Account/AccountDestroyTest.php`
Valida remocao de conta.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_own_account` | Remocao de conta propria |
| `test_cannot_delete_other_user_account` | Conta de terceiro retorna 403 |
| `test_deleting_default_assigns_new_default` | Remove default reassina para outra |
| `test_can_delete_last_account` | Pode remover ultima conta |
| `test_can_delete_account_outside_edit_limit` | Remocao fora do limite de edicao |

---

## Companies (V3)

### `tests/Feature/Asset/AssetCategoriesTest.php`
Valida listagem de categorias.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_active_categories` | Retorna categorias ativas ordenadas |
| `test_cannot_list_categories_without_authentication` | Sem auth retorna 401 |
| `test_categories_are_ordered_by_name` | Ordenacao alfabetica |

### `tests/Feature/Asset/AssetQuickTest.php`
Valida listagem rapida de empresas com posicoes abertas.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_quick_companies_from_open_positions` | Lista empresas das posicoes abertas do usuario ordenadas por data |
| `test_quick_companies_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Asset/AssetSearchTest.php`
Valida busca de ativos.

| Metodo | Cenario |
|--------|---------|
| `test_can_search_assets_by_ticker_code` | Busca por codigo do ticker |
| `test_can_search_assets_by_company_name` | Busca por nome da empresa |
| `test_can_filter_search_by_category` | Filtro por categoria |
| `test_search_excludes_inactive_tickers` | Exclui tickers inativos |
| `test_search_requires_minimum_characters` | Busca minima de caracteres |
| `test_search_respects_limit_parameter` | Limite de resultados |
| `test_cannot_search_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Asset/AssetShowTest.php`
Valida detalhes do ativo.

| Metodo | Cenario |
|--------|---------|
| `test_can_view_asset_details` | Retorna dados completos do ativo |
| `test_returns_404_for_nonexistent_asset` | Ativo inexistente retorna 404 |
| `test_cannot_view_asset_without_authentication` | Sem auth retorna 401 |

---

## Consolidated (V4)

### `tests/Feature/Consolidated/ConsolidatedIndexTest.php`
Valida listagem de posicoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_positions` | Lista apenas posicoes do usuario |
| `test_can_filter_by_search` | Filtro por busca |
| `test_index_validates_search_param` | Validacao do parametro search |
| `test_cannot_list_positions_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedShowTest.php`
Valida detalhes da posicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_view_own_position` | Acesso a posicao propria |
| `test_cannot_view_other_user_position` | Posicao de terceiro retorna 403 |
| `test_returns_404_for_nonexistent_position` | Posicao inexistente retorna 404 |

### `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`
Valida resumo das posicoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_summary` | Retorna resumo das posicoes |
| `test_summary_excludes_closed_positions` | Exclui posicoes fechadas |
| `test_cannot_get_summary_without_authentication` | Sem auth retorna 401 |
| `test_summary_includes_treasure_categories` | Inclui categorias de tesouro |

### `tests/Feature/Consolidated/ConsolidatedClosedTest.php`
Valida listagem de posicoes fechadas.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_only_closed_positions_from_authenticated_user` | Lista apenas posicoes fechadas do usuario |
| `test_can_filter_closed_positions_by_search` | Filtro por busca em posicoes fechadas |
| `test_closed_endpoint_validates_query_params` | Validacao de parametros de query |
| `test_closed_endpoint_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedOverviewTest.php`
Valida endpoint de overview/dashboard consolidado.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_overview_with_expected_business_metrics` | Retorna metricas de negocio (posicoes ativas, ganhos de capital, alocacao por categoria/instituicao/segmento/setor) |
| `test_overview_only_uses_authenticated_user_data` | Isolamento de dados - apenas dados do usuario autenticado |
| `test_cannot_get_overview_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Consolidated/ConsolidatedDestroyTest.php`
Valida remocao de posicoes consolidadas.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_consolidated_and_transactions` | Remove posicao e suas transacoes |
| `test_cannot_delete_other_user_consolidated` | Posicao de terceiro retorna 403 |
| `test_cannot_delete_consolidated_without_authentication` | Sem auth retorna 401 |

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
| `test_can_update_company_transaction` | Atualizacao de transacao de acao |
| `test_can_update_treasure_transaction` | Atualizacao de transacao de tesouro |
| `test_cannot_update_transaction_outside_position_limit` | Fora do limite de edicao |

### `tests/Feature/Consolidated/ConsolidatedTransactionDestroyTest.php`
Valida remocao de transacoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_company_transaction` | Remocao de transacao de acao |
| `test_can_delete_treasure_transaction` | Remocao de transacao de tesouro |
| `test_can_delete_transaction_outside_edit_limit` | Remocao fora do limite de edicao |

---

## Portfolio (V5)

### `tests/Feature/Portfolio/PortfolioIndexTest.php`
Valida listagem de portfolios.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_portfolios` | Lista apenas portfolios do usuario |
| `test_can_filter_portfolios_by_name` | Filtro por nome |
| `test_cannot_list_portfolios_without_authentication` | Sem auth retorna 401 |

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
| `test_update_requires_all_fields` | Todos os campos obrigatorios |
| `test_cannot_update_portfolio_outside_limit` | Fora do limite de edicao |

### `tests/Feature/Portfolio/PortfolioDestroyTest.php`
Valida remocao de portfolio.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_own_portfolio` | Soft delete de portfolio |
| `test_cannot_delete_other_user_portfolio` | Portfolio de terceiro retorna 403 |
| `test_cannot_delete_without_authentication` | Sem auth retorna 401 |
| `test_can_delete_portfolio_outside_edit_limit` | Remocao fora do limite de edicao |

### `tests/Feature/Portfolio/CompositionStoreTest.php`
Valida adicao de composicoes.

| Metodo | Cenario |
|--------|---------|
| `test_can_add_company_composition` | Adicao de acao a carteira |
| `test_can_add_treasure_composition` | Adicao de tesouro a carteira |
| `test_can_add_single_company_with_shorthand_payload` | Payload simplificado |
| `test_can_add_multiple_companies_with_ticker_percentage_map` | Multiplos ativos |
| `test_cannot_add_to_other_user_portfolio` | Portfolio de terceiro retorna 403 |
| `test_percentage_must_be_valid` | Validacao de percentual |
| `test_asset_must_exist_and_be_active` | Ativo deve existir e estar ativo |

### `tests/Feature/Portfolio/CompositionUpdateTest.php`
Valida atualizacao de composicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_own_composition` | Atualizacao de percentual |
| `test_cannot_update_other_user_composition` | Composicao de terceiro retorna 403 |
| `test_percentage_must_be_valid_on_update` | Validacao de percentual |
| `test_cannot_update_composition_outside_limit` | Fora do limite de edicao |

### `tests/Feature/Portfolio/CompositionUpdateBatchTest.php`
Valida atualizacao em lote.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_batch` | Atualizacao em lote |
| `test_cannot_update_batch_with_other_user_composition` | Composicoes de terceiro retornam 403 |

### `tests/Feature/Portfolio/CompositionDestroyTest.php`
Valida remocao de composicao.

| Metodo | Cenario |
|--------|---------|
| `test_can_remove_composition` | Remocao simples |
| `test_can_save_to_history_on_remove` | Remocao com historico |
| `test_cannot_remove_other_user_composition` | Composicao de terceiro retorna 403 |
| `test_cannot_remove_without_authentication` | Sem auth retorna 401 |
| `test_can_remove_composition_outside_edit_limit` | Remocao fora do limite de edicao |

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
| `test_crossing_returns_dividend_received_from_earnings` | Retorna dividendos recebidos |
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

## Subscription (V7)

### `tests/Feature/Subscription/SubscriptionLimitsTest.php`
Valida limites de assinatura.

| Metodo | Cenario |
|--------|---------|
| `test_user_receives_free_subscription_on_create` | Usuario novo recebe plano free |
| `test_free_plan_blocks_second_account_creation` | Plano free bloqueia 2a conta |
| `test_free_plan_has_limited_crossing_access` | Plano free nao tem crossing completo |
| `test_composition_limits_apply_per_portfolio` | Limites de composicao por portfolio |

### `tests/Feature/Subscription/SubscriptionResourceLocksTest.php`
Valida campos `is_locked` nos resources quando limites sao excedidos.

| Metodo | Cenario |
|--------|---------|
| `test_portfolio_resource_shows_is_locked_true_when_over_limit` | Portfolio mostra is_locked=true quando acima do limite |
| `test_portfolio_resource_shows_is_locked_false_when_under_limit` | Portfolio mostra is_locked=false quando dentro do limite |
| `test_composition_resource_shows_is_locked_true_when_over_limit` | Composition mostra is_locked=true quando acima do limite |

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
| `test_billing_issue_logs_warning_and_returns_success` | BILLING_ISSUE loga warning e retorna sucesso |
| `test_product_change_upgrade_applies_immediately` | PRODUCT_CHANGE upgrade aplica imediatamente |

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
| `test_current_subscription_recalculates_usage_and_relinks_to_active_subscription` | Recalcula uso e vincula a assinatura ativa |
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

## Earnings (V8)

### `tests/Feature/Earning/EarningIndexTest.php`
Valida listagem de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_own_earnings` | Lista proventos agrupados por data |
| `test_earnings_are_paginated_by_date_groups_with_five_dates_per_page` | Paginacao por grupos de data (5 datas por pagina) |
| `test_cannot_list_earnings_without_authentication` | Sem auth retorna 401 |
| `test_index_validates_search_param` | Validacao do parametro de busca |

### `tests/Feature/Earning/EarningShowTest.php`
Valida visualizacao de provento individual.

| Metodo | Cenario |
|--------|---------|
| `test_can_show_own_earning` | Visualizacao de provento proprio com dados completos |
| `test_cannot_show_other_user_earning` | Provento de terceiro retorna 404 |
| `test_cannot_show_earning_without_authentication` | Sem auth retorna 401 |
| `test_returns_404_for_nonexistent_earning` | ID inexistente retorna 404 |

### `tests/Feature/Earning/EarningStoreTest.php`
Valida criacao de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_create_earning` | Criacao de provento com dados validos |
| `test_cannot_create_earning_for_other_user` | Nao pode criar para consolidated de outro usuario (404) |
| `test_create_validation_fails` | Validacao de campos obrigatorios |

### `tests/Feature/Earning/EarningUpdateTest.php`
Valida atualizacao de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_update_earning` | Atualizacao de provento com dados validos |
| `test_cannot_update_other_user_earning` | Provento de terceiro retorna 404 |
| `test_cannot_update_earning_without_authentication` | Sem auth retorna 401 |
| `test_cannot_update_with_invalid_consolidated_id` | consolidated_id de outro usuario retorna 404 |

### `tests/Feature/Earning/EarningDestroyTest.php`
Valida remocao de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_delete_earning` | Remocao de provento proprio |
| `test_cannot_delete_other_user_earning` | Provento de terceiro retorna 404 |
| `test_cannot_delete_earning_without_authentication` | Sem auth retorna 401 |

### `tests/Feature/Earning/EarningTypeIndexTest.php`
Valida listagem de tipos de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_list_earning_types` | Lista tipos de proventos disponiveis |
| `test_requires_authentication` | Sem auth retorna 401 |

### `tests/Feature/Earning/EarningSummaryTest.php`
Valida resumo e agrupamento de proventos.

| Metodo | Cenario |
|--------|---------|
| `test_can_get_summary` | Retorna resumo com totais de proventos |
| `test_summary_respects_date_range` | Filtro por range de datas no resumo |
| `test_can_get_grouped_by_month` | Agrupamento de proventos por mes |
| `test_grouped_respects_date_range` | Filtro por range de datas no agrupamento |
| `test_cannot_get_summary_without_authentication` | Sem auth retorna 401 |
| `test_cannot_get_grouped_without_authentication` | Sem auth retorna 401 |
| `test_returns_error_for_invalid_group_parameter` | Parametro group invalido retorna 422 |

---

## Health

### `tests/Feature/HealthCheckTest.php`
Valida endpoint de health check.

| Metodo | Cenario |
|--------|---------|
| `test_health_endpoint_returns_success` | `GET /api/health` retorna 200 |

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

*Ultima atualizacao: Fevereiro 2026 - 221 testes implementados*

## Historico de Implementacoes

### Fase 1 - Modulo Earnings (V8) - 12 testes adicionados

**Arquivo:** `tests/Feature/Earning/EarningShowTest.php` (NOVO)
- ✅ `test_can_show_own_earning` - Visualizacao de provento proprio
- ✅ `test_cannot_show_other_user_earning` - Isolamento de dados (404)
- ✅ `test_cannot_show_earning_without_authentication` - Auth obrigatoria (401)
- ✅ `test_returns_404_for_nonexistent_earning` - Provento inexistente

**Arquivo:** `tests/Feature/Earning/EarningUpdateTest.php` (+3 metodos)
- ✅ `test_cannot_update_other_user_earning` - Isolamento de dados (404)
- ✅ `test_cannot_update_earning_without_authentication` - Auth obrigatoria (401)
- ✅ `test_cannot_update_with_invalid_consolidated_id` - Validacao de consolidated (404)

**Arquivo:** `tests/Feature/Earning/EarningDestroyTest.php` (+2 metodos)
- ✅ `test_cannot_delete_other_user_earning` - Isolamento de dados (404)
- ✅ `test_cannot_delete_earning_without_authentication` - Auth obrigatoria (401)

**Arquivo:** `tests/Feature/Earning/EarningSummaryTest.php` (+3 metodos)
- ✅ `test_cannot_get_summary_without_authentication` - Auth obrigatoria (401)
- ✅ `test_cannot_get_grouped_without_authentication` - Auth obrigatoria (401)
- ✅ `test_returns_error_for_invalid_group_parameter` - Validacao de parametro (422)

### Fase 2 - Modulo Subscription (V7) - 4 testes adicionados

**Arquivo:** `tests/Feature/Subscription/SubscriptionResourceLocksTest.php` (NOVO)
- ✅ `test_portfolio_resource_shows_is_locked_true_when_over_limit` - Portfolio bloqueado acima do limite
- ✅ `test_portfolio_resource_shows_is_locked_false_when_under_limit` - Portfolio desbloqueado dentro do limite
- ✅ `test_composition_resource_shows_is_locked_true_when_over_limit` - Composition bloqueada acima do limite

**Arquivo:** `tests/Feature/Subscription/RevenueCatWebhookTest.php` (+2 metodos)
- ✅ `test_billing_issue_logs_warning_and_returns_success` - BILLING_ISSUE loga warning
- ✅ `test_product_change_upgrade_applies_immediately` - PRODUCT_CHANGE upgrade aplica imediatamente

---

## ✅ Cobertura Completa

Todos os 221 testes estao implementados e documentados. Nao ha gaps de cobertura pendentes.
