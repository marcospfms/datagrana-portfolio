# Padrões de Criação de Endpoints (API)

Este documento padroniza o fluxo de desenvolvimento de novos endpoints na API do **Mecanix Core**, garantindo consistência, segurança e manutenibilidade.

---

## 🏗️ Arquitetura em Camadas

O desenvolvimento de funcionalidades no backend segue uma arquitetura MVC estrita com camadas de suporte:

1.  **Model Layer (`App\Models`)**:
    -   Definição da estrutura de dados, relacionamentos e casts.
    -   **Regra de Ouro**: Todo model deve pertencer a um `User`, seja diretamente (`user_id`) ou indiretamente (via relacionamento pai, ex: `Vehicle` -> `Customer` -> `User`).

2.  **Validation Layer (`App\Http\Requests`)**:
    -   Validação de entrada separada do controller.
    -   Um Request para criação (`Store`) e outro para atualização (`Update`).

3.  **Transformation Layer (`App\Http\Resources`)**:
    -   Transformação de dados para JSON.
    -   Padronização de respostas (snake_case, datas ISO).

4.  **Controller Layer (`App\Http\Controllers\Api`)**:
    -   Orquestração da lógica.
    -   **Segurança**: Verificação explícita de propriedade (`where('user_id', auth()->id())` ou via relacionamento pai).
    -   Retorno padronizado via `BaseController`.

5.  **Route Layer (`routes/api.php`)**:
    -   Definição de rotas protegidas por `auth:sanctum`.

---

## 📏 Padrões de Implementação

### 1. Models
Sempre defina `$fillable` e `$casts`. Relacionamentos devem ser tipados.
- Defina `$fillable` e `$casts`.
- Relacionamentos tipados.
- Regra de ouro: pertença ao `User` (direto ou indireto).

### 2. Form Requests
Nunca valide dados diretamente no Controller.
- Criar `StoreXRequest` e `UpdateXRequest` com `rules()`.

### 3. Resources
Estenda `JsonResource`. Use `whenLoaded` para relacionamentos.
- Decimais como string.
- Relacionamentos via `whenLoaded()`.

### 4. Controllers
Estenda `BaseController`. Implemente os métodos CRUD padrão (`index`, `store`, `show`, `update`, `destroy`).

**Segurança Obrigatória:**
- Sempre filtrar por `user_id` (ou pelo relacionamento pai) ao buscar por `id`.

---

## ✅ Checklist de Criação (Fluxo Padrão)

Ao criar um novo CRUD, siga esta ordem exata:

### 1. Database & Model
- [ ] Criar Migration: `php artisan make:migration create_table_name`
- [ ] Criar Model: `php artisan make:model Name`
- [ ] Definir `$fillable`, `$casts` e relacionamentos no Model.
- [ ] Garantir relacionamento com `User` (direto ou indireto) na migration e no Model.

### 2. Validation (Requests)
- [ ] Criar Request de Store: `php artisan make:request StoreNameRequest`
- [ ] Criar Request de Update: `php artisan make:request UpdateNameRequest`
- [ ] Definir regras de validação (`rules()`).

### 3. Transformation (Resource)
- [ ] Criar Resource: `php artisan make:resource NameResource`
- [ ] Definir array de retorno em `toArray()`.

### 4. Controller
- [ ] Criar Controller: `php artisan make:controller Api/NameController`
- [ ] Estender `BaseController`.
- [ ] Implementar métodos CRUD usando os Requests e Resources criados.
- [ ] **Crucial**: Adicionar verificação de propriedade (`user_id` ou via pai) em todas as queries.

### 5. Routes
- [ ] Adicionar rotas em `routes/api.php` dentro do grupo `auth:sanctum`.
- [ ] Usar `Route::apiResource('names', NameController::class);` se possível.

### 6. Tests
- [ ] Criar Feature Test: `php artisan make:test Api/NameTest`
- [ ] Testar fluxo feliz (criação, listagem).
- [ ] Testar segurança (tentar acessar dado de outro usuário).

---

## 🧩 Exemplo de Controller Completo
Use os controllers reais em `app/Http/Controllers/Api` como referência (padrão: scoping por usuário, Form Requests e Resources).
