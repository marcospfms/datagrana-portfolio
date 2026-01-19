# Resources - Padrões e Documentação

## 📐 Padrões e Convenções

### Estrutura de Arquivo
Resources seguem `App\\Http\\Resources\\*` e expõem apenas os campos necessários no `toArray()`.

### Convenções de Nomenclatura
- **Arquivo**: `{Model}Resource.php` (ex: `CustomerResource.php`)
- **Classe**: `{Model}Resource` (ex: `class CustomerResource`)
- **Namespace**: `App\Http\Resources`

### Regras de Transformação

1. **Incluir sempre**: `id`, `created_at`, `updated_at`
2. **Nomes de campos**: Sempre usar `snake_case` nas chaves do JSON
3. **Campos sensíveis**: Nunca expor senhas, tokens, dados internos
4. **Relacionamentos**: Usar `whenLoaded()` para lazy loading
5. **Formato de datas**: Usar ISO 8601 (automático do Laravel)
6. **Decimais**: Retornar como string para precisão (`'45.00'`)

### Exemplos por Tipo

**Resource Básico**: retorna campos simples (id/nome/timestamps) e decimais como string.

**Resource com Relacionamentos**: usa `whenLoaded()` para evitar consultas desnecessárias.

**Resource com Pivot Data**: usa `whenPivotLoaded()` para expor dados do pivot.

---

## 📦 Resources Implementados

### UserResource
**Arquivo**: `app/Http/Resources/UserResource.php`

**Campos:**
- `id`: int
- `name`: string
- `email`: string
- `created_at`: datetime
- `updated_at`: datetime

### CompanyResource
**Arquivo**: `app/Http/Resources/CompanyResource.php`

**Campos:**
- `id`: int
- `name`: string
- `cnpj`: string
- `logo_url`: string|null
- `created_at`: datetime
- `updated_at`: datetime

### CustomerResource
**Arquivo**: `app/Http/Resources/CustomerResource.php`

**Campos:**
- `id`: int
- `name`: string
- `tax_id`: string
- `phone`: string|null
- `email`: string|null
- `created_at`: datetime
- `updated_at`: datetime

### ServiceResource
**Arquivo**: `app/Http/Resources/ServiceResource.php`

**Campos:**
- `id`: int
- `name`: string
- `description`: string|null
- `price`: string (decimal)
- `created_at`: datetime
- `updated_at`: datetime

### PartResource
**Arquivo**: `app/Http/Resources/PartResource.php`

**Campos:**
- `id`: int
- `name`: string
- `description`: string|null
- `price`: string (decimal)
- `cost_price`: string (decimal)
- `created_at`: datetime
- `updated_at`: datetime

**Nota**: `stock_quantity` vive no pivot `part_company` e deve ser exposto apenas em endpoints que carregam o pivot com `whenPivotLoaded()`.

### SubscriptionPlanResource (V1.4)
**Arquivo**: `app/Http/Resources/SubscriptionPlanResource.php`

**Campos:**
- `id`: int
- `name`: string
- `description`: string|null
- `price_monthly`: string (decimal)
- `is_active`: bool
- `limits.companies`: int|null
- `limits.customers`: int|null
- `limits.vehicles`: int|null
- `limits.checklist_templates`: int|null
- `limits.checklists_per_month`: int|null
- `limits.parts`: int|null
- `limits.services`: int|null
- `limits.service_orders_per_month`: int|null

### UserSubscriptionResource (V1.4)
**Arquivo**: `app/Http/Resources/UserSubscriptionResource.php`

**Campos:**
- `id`: int
- `subscription_plan_id`: int
- `plan_name`: string
- `price_monthly`: string (decimal)
- `status`: string
- `starts_at`: datetime
- `ends_at`: datetime|null
- `renews_at`: datetime|null
- `limits.companies`: int|null
- `limits.customers`: int|null
- `limits.vehicles`: int|null
- `limits.checklist_templates`: int|null
- `limits.checklists_per_month`: int|null
- `limits.parts`: int|null
- `limits.services`: int|null
- `limits.service_orders_per_month`: int|null

---

## 🎯 Quando Criar um Novo Resource

1. **Um recurso por Model** - Cada model deve ter seu Resource
2. **Expor apenas o necessário** - Não expor dados internos
3. **Consistência** - Todos resources devem incluir `id`, timestamps
4. **Versionamento** - Para mudanças breaking, criar `{Model}ResourceV2`

---

## ✅ Checklist para Novo Resource

- [ ] Criar arquivo em `app/Http/Resources/{Model}Resource.php`
- [ ] Estender `JsonResource`
- [ ] Implementar `toArray(Request $request)`
- [ ] Incluir `id`, `created_at`, `updated_at`
- [ ] Adicionar campos específicos do model
- [ ] Usar `whenLoaded()` para relacionamentos
- [ ] Testar formato de saída
- [ ] Documentar em `docs/resources.md`
