# Controllers - Revisão e Análise

## 📑 Índice
- [Padrões e Convenções](#-padrões-e-convenções)
- [Status Geral](#-status-geral)
- [Controllers Implementados](#-controllers-implementados)
- [Controllers Pendentes](#-controllers-pendentes-para-implementação)

---

## 📐 Padrões e Convenções

### Estrutura de Arquivo
Controllers ficam em `App\\Http\\Controllers\\Api`, estendem `BaseController`, usam Form Requests (Store/Update) e API Resources.

**Nota (Admin Web)**: a interface administrativa usa controllers em `App\\Http\\Controllers\\Admin` e rotas em `routes/web.php` (Inertia/Vue), protegidas pelo middleware `admin` (campo `users.is_admin`).

### Convenções de Nomenclatura
- **Arquivo**: `{Model}Controller.php` (ex: `CustomerController.php`)
- **Classe**: `{Model}Controller extends BaseController`
- **Namespace**: `App\Http\Controllers\Api`

### Métodos Padrão CRUD

| Método | Rota | HTTP | Descrição |
|--------|------|------|-----------|
| `index()` | `/{resources}` | GET | Listar todos |
| `store()` | `/{resources}` | POST | Criar novo |
| `show()` | `/{resources}/{id}` | GET | Visualizar um |
| `update()` | `/{resources}/{id}` | PUT/PATCH | Atualizar |
| `destroy()` | `/{resources}/{id}` | DELETE | Deletar |

### Escopo de Segurança (CRÍTICO)

**SEMPRE validar propriedade antes de qualquer operação:**
- Exemplo (direto): filtrar por `user_id` na query.
- Exemplo (indireto): filtrar via relacionamento pai (ex: `vehicle.customer.user_id`).

**Usar relacionamentos do usuário para criar:**
- Criação via relacionamento do usuário garante o escopo.

### Respostas Padrão

**Sucesso com dados:**
`sendResponse(resource, 'Message')`

**Sucesso com collection:**
`sendResponse(Resource::collection($items), 'Message')`

**Sucesso sem dados:**
`sendResponse([], 'Message')`

**Erro 404:**
`sendError('Not found')`

**Erro 409 (Conflict):**
`sendError('Already exists', [], 409)`

**Regra**: usar sempre `sendResponse`/`sendError` (evitar `abort()` e `response()->json()` diretos) para manter o contrato `success/data/message`.

### Operações Pivot (Many-to-Many)

**Attach:**
- Validar payload.
- Validar propriedade dos dois lados (item + company).
- Evitar duplicidade (409).
- `attach()` com dados do pivot quando necessário.

**Detach:**
- Validar propriedade e então `detach()`.

**Update Pivot:**
- Validar payload e `updateExistingPivot()` quando o vínculo existir.

---

Todos os controllers existentes estão **funcionais e compatíveis** com a nova estrutura do banco de dados. Não foram identificadas breaking changes.

---

## 📋 Controllers Implementados

### 1. BaseController

**Localização:** `app/Http/Controllers/Api/BaseController.php`

**Responsabilidade:** Classe base para padronizar respostas JSON da API.

**Métodos:**
- `sendResponse($result, $message)` - Resposta de sucesso
- `sendError($error, $errorMessages = [], $code = 404)` - Resposta de erro

**Status:** ✅ **OK** - Não requer alterações

---

### 2. AuthController

**Localização:** `app/Http/Controllers/Api/AuthController.php`

**Endpoints:**
- `POST /login` - Autenticação de usuário
- `GET /me` - Dados do usuário autenticado

**Métodos:**
- `login(Request $request)`
- `me(Request $request)`

**Validações:**
- Login: email, password, device_name (required)

**Resposta:**
- Login: `{ token, user }` (UserResource)
- Me: UserResource

**Status:** ✅ **OK** - Compatível com schema atual

---

### 3. UserController

**Localização:** `app/Http/Controllers/Api/UserController.php`

**Endpoints:**
- `PUT /user` - Atualizar dados do usuário
- `PUT /user/password` - Atualizar senha do usuário

**Form Requests:**
- `UpdateUserRequest` - Valida name e email (unique ignorando o próprio usuário)
- `UpdatePasswordRequest` - Valida current_password e password com confirmação

**Resposta:**
- Update: UserResource
- UpdatePassword: array vazio `[]`

**Status:** ✅ **OK** - Implementação correta

---

### 4. CompanyController

**Localização:** `app/Http/Controllers/Api/CompanyController.php`

**Endpoints:** *(Resource completo)*
- `GET /companies` - Listar empresas do usuário
- `POST /companies` - Criar nova empresa
- `GET /companies/{id}` - Visualizar empresa específica
- `PUT/PATCH /companies/{id}` - Atualizar empresa
- `DELETE /companies/{id}` - Deletar empresa

**Form Requests:**
- `StoreCompanyRequest` - Valida name e cnpj (unique)
- `UpdateCompanyRequest` - Valida name e cnpj (unique ignorando a própria empresa)

**Escopo de Segurança:** ✅ **Implementado corretamente**
- Index via relacionamento do usuário.
- Show/Update/Delete com filtro por `user_id`.

**Resposta:** CompanyResource

**Status:** ✅ **OK** - Implementação completa e segura

---

## 📦 Resources Implementados

### UserResource
**Campos exportados:**
- id, name, email, created_at, updated_at

**Status:** ✅ **OK**

### CompanyResource
**Campos exportados:**
- id, name, cnpj, created_at, updated_at

**Status:** ✅ **OK**

---

## 🚀 Controllers Pendentes para Implementação

Atualmente, as pendências principais no backend são relacionadas ao módulo de **Ordens de Serviço (Service Orders)**.

### 1. ServiceOrderController (Alta Prioridade)
**Status:** ⏳ Pendente (models/migrations existem; controllers ainda não)

**Escopo esperado:**
- CRUD de `service_order` com scoping via `company.user_id`
- Contagem mensal integrada ao sistema de assinatura (limite `service_orders_per_month`)

### 2. Controllers auxiliares de Service Order (Alta Prioridade)
**Status:** ⏳ Pendente

- `ServiceOrderVehicleController` (vínculo OS ↔ veículos)
- `ServiceOrderPartController` (snapshot de peças por veículo da OS)
- `ServiceOrderServiceController` (snapshot de serviços por veículo da OS)

---

## ⚠️ Observações Importantes

### Escopo de Segurança
Todos os controllers novos devem implementar escopo por usuário:
- Preferir `request->user()->relation()` para listar/criar.
- Para show/update/delete: validar propriedade explicitamente.

### Relacionamentos Many-to-Many
Para controllers que gerenciam relacionamentos pivot (`part_company`, `service_company`, `checklist_template_company`):
- `attach()`, `detach()` e `updateExistingPivot()` conforme necessidade.

### Form Requests
Todos os controllers devem usar Form Requests para validação, seguindo o padrão:
- `StoreXxxRequest` - Para criar
- `UpdateXxxRequest` - Para atualizar

---

## 📊 Resumo

| Controller | Status | Compatível com Schema | Precisa Ajustes |
|------------|--------|----------------------|----------------|
| BaseController | ✅ Implementado | ✅ Sim | ❌ Não |
| AuthController | ✅ Implementado | ✅ Sim | ❌ Não |
| UserController | ✅ Implementado | ✅ Sim | ❌ Não |
| CompanyController | ✅ Implementado | ✅ Sim | ❌ Não |
| CustomerController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleController | ✅ Implementado | ✅ Sim | ❌ Não |
| ChecklistTemplateController | ✅ Implementado | ✅ Sim | ❌ Não |
| ChecklistItemController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleChecklistController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleChecklistItemController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleMileageHistoryController | ✅ Implementado | ✅ Sim | ❌ Não |
| PartController | ✅ Implementado | ✅ Sim | ❌ Não |
| ServiceController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleTypeController | ✅ Implementado | ✅ Sim | ❌ Não |
| DashboardController | ✅ Implementado | ✅ Sim | ❌ Não |
| VehicleChecklistPdfController | ✅ Implementado | ✅ Sim | ❌ Não |
| SubscriptionController | ✅ Implementado | ✅ Sim | ❌ Não |
| ServiceOrderController | ⏳ Pendente | - | - |

---

## 👨‍💼 Controllers Admin (V1.4.1 + V1.6)

**Localização:** `app/Http/Controllers/Admin`

**Proteção:** Middleware `admin` (verifica `users.is_admin`)

**Framework:** Inertia.js + Vue 3 (SSR)

**Rotas:** `routes/web.php` (grupo `/admin`)

### Controllers Implementados

| Controller | Responsabilidade | Status |
|------------|------------------|--------|
| **AdminDashboardController** | Dashboard administrativo com métricas | ✅ Implementado |
| **AdminPlanController** | CRUD de planos de assinatura | ✅ Implementado |
| **AdminUserController** | Gestão de usuários e assinaturas | ✅ Implementado |
| **AdminSubscriptionController** | Gestão de assinaturas | ✅ Implementado |
| **AdminBillingController** | Interface de cobranças manuais | ✅ Implementado |
| **AdminInviteCodeController** | Gestão de códigos de convite | ✅ Implementado |

### Funcionalidades Principais

#### AdminDashboardController
**Endpoints:**
- `GET /admin/dashboard` - Métricas de assinaturas, usuários e uso

**Métricas exibidas:**
- Total de usuários
- Usuários por plano
- Taxa de conversão trial→pago
- Uso médio vs limites
- Receita recorrente (MRR)

#### AdminPlanController
**Endpoints:**
- `GET /admin/plans` - Listar planos
- `GET /admin/plans/create` - Formulário de criação
- `POST /admin/plans` - Criar plano
- `GET /admin/plans/{id}/edit` - Formulário de edição
- `PUT /admin/plans/{id}` - Atualizar plano
- `DELETE /admin/plans/{id}` - Deletar plano

#### AdminUserController
**Endpoints:**
- `GET /admin/users` - Listar usuários (paginado, com busca)
- `GET /admin/users/{id}` - Visualizar usuário (com histórico)
- `PUT /admin/users/{id}` - Atualizar dados do usuário
- `DELETE /admin/users/{id}` - Deletar usuário

**Recursos:**
- Busca por nome, email, CPF
- Filtros por plano, status
- Edição de dados básicos
- Toggle de admin

#### AdminSubscriptionController
**Endpoints:**
- `GET /admin/subscriptions` - Listar assinaturas
- `POST /admin/users/{userId}/subscription` - Atribuir plano
- `PUT /admin/subscriptions/{id}` - Editar limites individuais
- `POST /admin/subscriptions/{id}/trial` - Iniciar trial
- `POST /admin/subscriptions/{id}/sync-usage` - Sincronizar contadores
- `GET /admin/users/{userId}/subscription-history` - Histórico

**Funcionalidades especiais:**
- Sobrescrever limites por usuário
- Períodos de teste customizados
- Sincronização manual de contadores

#### AdminBillingController
**Endpoints:**
- `GET /admin/billing` - Dashboard de cobranças
- `GET /admin/billing/pending` - Assinaturas pendentes
- `GET /admin/billing/paid` - Histórico de pagos
- `POST /admin/billing/{subscriptionId}/payment` - Registrar pagamento
- `GET /admin/billing/users/{userId}/payments` - Histórico do usuário

**Recursos:**
- Registro manual de pagamento com:
  - Método (Pix, TED, Boleto, Dinheiro, Cartão)
  - Comprovante (upload)
  - Notas/observações
  - Metadata JSON
- Histórico completo imutável
- Rastreamento de quem registrou

#### AdminInviteCodeController
**Endpoints:**
- `GET /admin/invite-codes` - Listar códigos
- `POST /admin/invite-codes` - Criar código
- `PUT /admin/invite-codes/{id}` - Atualizar código
- `DELETE /admin/invite-codes/{id}` - Deletar código
- `GET /admin/invite-codes/{id}/usage` - Ver usos

**Recursos:**
- 4 tipos: `user_referral`, `campaign`, `partnership`, `admin`
- Controle de usos máximos
- Vinculação a planos
- Trial days customizado
- Rastreamento completo

---
