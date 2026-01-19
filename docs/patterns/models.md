# Models - Relacionamentos e Estrutura

## Indice
- [Padrões e Convenções](#-padrões-e-convenções)
- [Resumo das Alteracoes](#resumo-das-alteracoes)
- [Models Implementados](#models-implementados)
- [Validação](#-validação-dos-models)

---

## 📐 Padrões e Convenções

### Estrutura de Arquivo
Model segue `App\\Models\\*`, define `$table`, `$fillable`, `$casts` e relacionamentos tipados.

### Convenções de Nomenclatura
- **Arquivo**: PascalCase, singular (`Customer.php`, `ServiceOrder.php`)
- **Classe**: Mesmo nome do arquivo (`class Customer`)
- **Tabela**: snake_case, singular (`$table = 'customer'`)
- **Relacionamentos**: 
  - Singular para `belongsTo`: `customer()`, `user()`
  - Plural para `hasMany`: `customers()`, `orders()`
  - Plural para `belongsToMany`: `companies()`, `parts()`

### Propriedades Padrão

**$fillable**
- Sempre definir campos que podem ser mass-assigned
- Usar array explícito (nunca `$guarded = []`)

**$casts**
- Definir via propriedade `$casts` ou metodo `casts()`
- `decimal:2` para valores monetarios
- `datetime` para timestamps adicionais

### Tipos de Relacionamento

**belongsTo** (muitos-para-um)
- Nome no singular (ex: `user()`).

**hasMany** (um-para-muitos)
- Nome no plural (ex: `customers()`).

**belongsToMany** (muitos-para-muitos)
- Sempre declarar tabela pivot, `withPivot()` e `withTimestamps()` quando aplicável.

### Pivot Tables
- Sempre usar `withTimestamps()` em relacionamentos pivot
- Usar `withPivot()` para campos adicionais
- Nome da tabela pivot: alfabético (`part_company`, não `company_part`)

---

## Resumo das Alteracoes

Todos os Models foram criados/atualizados para refletir a nova arquitetura de banco de dados onde:
- **Propriedade do Usuário**: `customer`, `part`, `service`, `checklist_template` pertencem ao `user`
- **Tabelas Pivô**: `part_company`, `service_company`, `checklist_template_company`
- **Hierarquia**: `customer` → `vehicle` (sem `company_id` no veículo)
- **Assinaturas (V1.4)**: `subscription_plan`, `user_subscription`, `subscription_usage` controlam limites e uso por período

---

## 1. User Model

**Fillable:** `name`, `email`, `password`, `cpf`, `phone`, `invite_code_id`, `is_admin`

**Casts:**
- `email_verified_at` → `datetime`
- `password` → `hashed`
- `is_admin` → `boolean`

**Relacionamentos:**
- `hasMany(Company)` - Um usuário possui várias empresas
- `hasMany(Customer)` - Um usuário possui vários clientes (global)
- `hasMany(Part)` - Um usuário possui várias peças
- `hasMany(Service)` - Um usuário possui vários serviços
- `hasMany(ChecklistTemplate)` - Um usuário possui vários templates de checklist
- `hasMany(UserSubscription)` - Histórico de assinaturas do usuário
- `hasOne(UserSubscription)` (`activeSubscription`) - Assinatura ativa (status=active, starts_at <= now, ends_at NULL ou > now); se houver múltiplas, prioriza plano pago
- `belongsTo(InviteCode, 'invite_code_id')` - Codigo de convite usado
- `hasMany(InviteCodeUsage)` - Usos do codigo de convite
- `hasMany(InviteCode, 'created_by_user_id')` - Codigos criados
- `hasMany(SubscriptionPayment, 'user_id')` - Pagamentos realizados
- `hasMany(SubscriptionPayment, 'recorded_by_user_id')` - Pagamentos registrados como admin

**Campos de Admin (V1.4.1):**
- `is_admin` (boolean) - controla acesso à área administrativa (`middleware: admin`)

**Campos de Convite (V1.6):**
- `cpf` (string, 11 chars, unique, nullable) - CPF validado
- `phone` (string, 15 chars, nullable) - WhatsApp para contato
- `invited_by_code_id` (FK nullable) - Código usado no cadastro
- `invited_by_user_id` (FK nullable) - Usuário que indicou

---

## 2. Company Model

**Fillable:** `user_id`, `name`, `cnpj`, `logo`

**Relacionamentos:**
- `belongsTo(User)` - Uma empresa pertence a um usuário
- `belongsToMany(Part)` via `part_company` - Peças disponíveis nesta empresa
  - Pivot: `stock_quantity`
- `belongsToMany(Service)` via `service_company` - Serviços disponíveis nesta empresa
- `belongsToMany(ChecklistTemplate)` via `checklist_template_company` - Templates disponíveis nesta empresa
- `hasMany(ServiceOrder)` - Ordens de serviço desta empresa

---

## 3. Customer Model ⭐ (Mudança Principal)

**Fillable:** `user_id`, `name`, `tax_id`, `phone`, `email`

**Relacionamentos:**
- `belongsTo(User)` - Um cliente pertence a um usuário (não a uma empresa)
- `hasMany(Vehicle)` - Um cliente possui vários veículos
- `hasMany(ServiceOrder)` - Um cliente possui várias ordens de serviço

**Unique Key:** `(user_id, tax_id)` - Tax ID único por usuário

---

## 4. Vehicle Model ⭐ (Mudança Principal)

**Fillable:** `customer_id`, `license_plate`, `model`, `model_year`, `color`

**Relacionamentos:**
- `belongsTo(Customer)` - Um veículo pertence a um cliente (não tem `company_id`)
- `hasMany(VehicleChecklist)` - Histórico de checklists do veículo
- `hasMany(ServiceOrderVehicle)` - Relação com ordens de serviço

**Unique Key:** `(customer_id, license_plate)` - Placa única por cliente

---

## 5. Part Model ⭐ (Mudança Principal)

**Fillable:** `user_id`, `name`, `description`, `price`, `cost_price`

**Casts:**
- `price` → `decimal:2`
- `cost_price` → `decimal:2`

**Relacionamentos:**
- `belongsTo(User)` - Uma peça pertence a um usuário (não a uma empresa)
- `belongsToMany(Company)` via `part_company` - Empresas onde esta peça está disponível
  - Pivot: `stock_quantity` - Estoque específico por loja

---

## 6. Service Model ⭐ (Mudança Principal)

**Fillable:** `user_id`, `name`, `description`, `price`

**Casts:**
- `price` → `decimal:2`

**Relacionamentos:**
- `belongsTo(User)` - Um serviço pertence a um usuário (não a uma empresa)
- `belongsToMany(Company)` via `service_company` - Empresas onde este serviço está disponível

---

## 7. VehicleType Model

**Fillable:** `name`

**Relacionamentos:**
- `hasMany(ChecklistTemplate)` - Templates de checklist associados a este tipo de veículo

---

## 8. ChecklistTemplate Model ⭐ (Mudança Principal)

**Fillable:** `user_id`, `vehicle_type_id`, `name`

**Relacionamentos:**
- `belongsTo(User)` - Um template pertence a um usuário (não a uma empresa)
- `belongsTo(VehicleType)` - Tipo de veículo associado (nullable)
- `belongsToMany(Company)` via `checklist_template_company` - Empresas onde este template está disponível
- `hasMany(ChecklistItem)` - Itens que compõem este template

---

## 9. ChecklistItem Model

**Fillable:** `checklist_template_id`, `name`, `description`, `order_index`

**Relacionamentos:**
- `belongsTo(ChecklistTemplate)` - Um item pertence a um template

---

## 10. SubscriptionPlan Model (V1.4)

**Tabela:** `subscription_plan`

**Relacionamentos:**
- `hasMany(UserSubscription)` - Assinaturas que foram criadas a partir do plano

**Notas:**
- `is_active=true` define se o plano aparece em `GET /api/subscription-plans`
- Limites `NULL` significam **ilimitado**

---

## 11. UserSubscription Model (V1.4)

**Tabela:** `user_subscription`

**Descrição:** Snapshot do plano no momento da atribuição, com vigência e status.

**Relacionamentos:**
- `belongsTo(User)` - Usuário dono da assinatura
- `belongsTo(SubscriptionPlan)` - Plano de origem (referência)
- `hasMany(SubscriptionUsage)` - Uso por período (ano/mês)

---

## 12. SubscriptionUsage Model (V1.4)

**Tabela:** `subscription_usage`

**Descrição:** Contadores de uso do usuário no período corrente (`period_year`/`period_month`).

**Relacionamentos:**
- `belongsTo(User)` - Usuário (para unicidade por período)
- `belongsTo(UserSubscription)` - Assinatura ativa no período

---

## 13. InviteCode Model (V1.6)

**Tabela:** `invite_code`

**Descrição:** Códigos de convite para rastreamento de origem de cadastros e promoções.

**Fillable:** `code`, `type`, `created_by_user_id`, `metadata`, `max_uses`, `used_count`, `subscription_plan_id`, `trial_days`, `expires_at`, `is_active`

**Casts:**
- `metadata` → `array`
- `expires_at` → `datetime`
- `is_active` → `boolean`

**Relacionamentos:**
- `belongsTo(User, 'created_by_user_id')` - Usuário que criou o código
- `belongsTo(SubscriptionPlan)` - Plano vinculado (nullable)
- `hasMany(InviteCodeUsage)` - Usos do código
- `hasMany(User, 'invited_by_code_id')` - Usuários que usaram este código

**Tipos (enum):** `user_referral`, `campaign`, `partnership`, `admin`

---

## 14. InviteCodeUsage Model (V1.6)

**Tabela:** `invite_code_usage`

**Descrição:** Registro de uso de códigos de convite (tabela pivot com histórico).

**Fillable:** `invite_code_id`, `user_id`, `used_at`

**Casts:**
- `used_at` → `datetime`

**Relacionamentos:**
- `belongsTo(InviteCode)` - Código usado
- `belongsTo(User)` - Usuário que usou

**Unique Key:** `(invite_code_id, user_id)`

---

## 15. SubscriptionPayment Model (V1.6)

**Tabela:** `subscription_payment`

**Descrição:** Histórico imutável de pagamentos registrados manualmente.

**Fillable:** `user_subscription_id`, `user_id`, `recorded_by_user_id`, `amount`, `payment_method`, `payment_date`, `notes`, `receipt_path`, `metadata`

**Casts:**
- `amount` → `decimal:2`
- `payment_date` → `datetime`
- `metadata` → `array`

**Relacionamentos:**
- `belongsTo(UserSubscription)` - Assinatura paga
- `belongsTo(User, 'user_id')` - Usuário que pagou
- `belongsTo(User, 'recorded_by_user_id')` - Admin que registrou

**Métodos de Pagamento (enum):** `pix`, `ted`, `boleto`, `dinheiro`, `cartao`, `outro`

---

## 16. AppSetting Model (V1.6)

**Tabela:** `app_settings`

**Descrição:** Configurações dinâmicas do sistema (chave-valor).

**Fillable:** `key`, `value`, `type`

**Casts:** Dinâmico baseado no campo `type`

**Tipos (enum):** `string`, `integer`, `boolean`, `json`

**Unique Key:** `key`

**Exemplos de configurações:**
- `maintenance_mode` (boolean)
- `registration_enabled` (boolean)
- `max_free_trial_days` (integer)
- `welcome_message` (string)
- `feature_flags` (json)

**Nota:** Sem relacionamentos - tabela de configuração pura

---

## 🔗 Relacionamentos Many-to-Many (Pivot Tables)

### part_company
- Conecta: `Part` ↔ `Company`
- Pivot Data: `stock_quantity`, `timestamps`

### service_company
- Conecta: `Service` ↔ `Company`
- Pivot Data: `timestamps`

### checklist_template_company
- Conecta: `ChecklistTemplate` ↔ `Company`
- Pivot Data: `timestamps`
- Unique Index: `unique_ct_company`

---

## ✅ Validação dos Models

Todos os models estão alinhados com:
- ✅ Migrations refatoradas (`2025_11_21_101500_create_core_tables.php`, `2025_11_21_101501_create_checklist_tables.php`)
- ✅ Documentação do banco (`docs/database.md`)
- ✅ Relacionamentos bidirecionais implementados
- ✅ Pivot tables com `withTimestamps()` e `withPivot()` configurados
