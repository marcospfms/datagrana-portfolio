# Tests - Padrões e Documentação

## 📐 Padrões e Convenções

### Estrutura de Arquivo
Estrutura recomendada: `Tests\\Feature\\Api\\{Context}Test` usando `RefreshDatabase`, com `setUp()` criando `User` + token Sanctum.

### Convenções de Nomenclatura
- **Arquivo**: `{Model}Test.php` (ex: `CustomerTest.php`, `ServiceTest.php`)
- **Classe**: `{Model}Test` (ex: `class CustomerTest`)
- **Métodos**: `test_can_{action}` ou `test_{condition}`
- **Namespace**: `Tests\Feature\Api`

### Organização de Testes por Arquivo

**Um arquivo por Controller/Contexto**
```
tests/Feature/Api/
├── UserTest.php          (User endpoints)
├── CompanyTest.php       (Company endpoints)
├── CustomerTest.php      (Customer endpoints)
├── ServiceTest.php       (Service endpoints)
└── PartTest.php          (Part endpoints)
```

---

## 🎯 Padrões de Nomenclatura de Testes

### Operações CRUD
- `test_can_list_{resources}()`
- `test_can_create_{resource}()`
- `test_can_show_{resource}()`
- `test_can_update_{resource}()`
- `test_can_delete_{resource}()`

### Validações
- `test_create_{resource}_validation_fails()`
- `test_cannot_{action}_with_invalid_data()`
- `test_cannot_use_existing_{field}()`

### Segurança e Escopo
- `test_cannot_show_other_user_{resource}()`
- `test_cannot_update_other_user_{resource}()`
- `test_cannot_delete_other_user_{resource}()`
- `test_requires_authentication()`

### Operações Pivot
- `test_can_attach_{resource}_to_{parent}()`
- `test_cannot_attach_{resource}_twice_to_same_{parent}()`
- `test_can_detach_{resource}_from_{parent}()`
- `test_can_update_{resource}_{parent}_stock()`

---

## 🔧 Setup Padrão

### Trait RefreshDatabase
- Sempre usar para testes de Feature
- Garante banco limpo em cada teste

### Propriedades Comuns
- `User $user`
- `string $token`

### Método setUp()
- Criar `User` via factory
- Gerar token: `$user->createToken(...)->plainTextToken`

### Seeders (quando necessário)
Alguns endpoints dependem de dados seedados (ex: planos de assinatura em `subscription_plan`).

- Para testes envolvendo limites/assinatura: rode `SubscriptionPlanSeeder`.
- O projeto já faz isso automaticamente no `tests/TestCase.php` quando a tabela `subscription_plan` existe.

---

## ✅ Assertions Comuns

### Status HTTP
- `200` sucesso
- `201` criado (somente se o controller retornar explicitamente)
- `401` não autenticado
- `403` proibido
- `404` não encontrado
- `409` conflito
- `422` erro de validação

### JSON Response
- `assertJson(['success' => true])`
- `assertJsonPath('data.field', 'value')`
- `assertJsonCount(n, 'data')`

### Database
- `assertDatabaseHas('table', [...])`
- `assertDatabaseMissing('table', [...])`

---

## 📝 Template Completo de Teste
Use os testes existentes em `tests/Feature/Api` como base (padrão: `setUp()` com `User` + token, e assertions em status/JSON/database).

---

## 📊 Testes Implementados

| Arquivo | Testes | Assertions | Cobertura |
|---------|--------|------------|-----------|
| UserTest.php | 7 | 14 | Profile, Password |
| CompanyTest.php | 11 | 22 | CRUD, Validations |
| CustomerTest.php | 10 | 21 | CRUD, Scope |
| ServiceTest.php | 12 | 25 | CRUD, Pivot |
| PartTest.php | 14 | 30 | CRUD, Pivot, Stock |
| **TOTAL** | **(atualize conforme a suíte)** | - | - |

**Observação**: além dos testes de API em `tests/Feature/Api`, existem testes da área web/admin em `tests/Feature` (ex: planos, usuários e assinaturas).

---

## ✅ Checklist para Novo Teste

- [ ] Criar arquivo `tests/Feature/Api/{Model}Test.php`
- [ ] Usar `RefreshDatabase` trait
- [ ] Implementar `setUp()` com user e token
- [ ] Testar LIST (index)
- [ ] Testar CREATE (store) + validação
- [ ] Testar SHOW
- [ ] Testar UPDATE
- [ ] Testar DELETE
- [ ] Testar escopo de segurança (outros usuários)
- [ ] Testar autenticação (401)
- [ ] Se houver pivot: testar attach/detach
- [ ] Executar `php artisan test --filter={Model}Test`
