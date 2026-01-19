# Tests - Registro

Este arquivo registra cada teste criado e seu objetivo.

## Auth (V1)

- `tests/Feature/Auth/GoogleAuthTest.php`: cobre login via Google (token valido/invalido), usuario inativo, criacao de usuario e revogacao de tokens antigos.
- `tests/Feature/Auth/MeEndpointTest.php`: valida `GET /api/auth/me` com token valido e bloqueia acessos sem token/invalido/revogado.
- `tests/Feature/Auth/LogoutTest.php`: valida `POST /api/auth/logout` e `POST /api/auth/logout-all`, incluindo revogacao de tokens.

## Health

- `tests/Feature/HealthCheckTest.php`: valida `GET /api/health` com resposta de sucesso.

## Configuracao de Ambiente de Testes

- `.env.testing`: usa SQLite em arquivo (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/testing.sqlite`) para evitar uso do banco real.
