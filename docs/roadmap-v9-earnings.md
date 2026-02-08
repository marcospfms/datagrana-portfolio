# Roadmap V9 - Proventos (Earnings)

**Status:** Planejamento  
**Dependencias:** V1-V8 (completos)  
**Objetivo:** Implementar modulo de proventos no `datagrana-portfolio` com CRUD, resumos e integracao futura com `company_earnings`.

---

## Resumo

O modulo de proventos cobre o registro de dividendos, JCP, rendimentos e outros ganhos associados a posicoes consolidadas.
O banco ja possui as migrations de `earning_type` e `earnings`:

- `database/migrations/earnings/2025_01_07_000001_create_earning_type_table.php`
- `database/migrations/earnings/2025_01_07_000003_create_earnings_table.php`

Este roadmap inclui:
- Models e relacionamentos
- Seed inicial dos tipos
- CRUD via API com validacoes
- Regras de negocio de valores liquido/bruto/imposto
- Endpoints de resumo para o app
- Testes completos
- Documentacao

---

## Fase 1 - Infraestrutura (Models + Relacionamentos + Seeders)

### 1.1 Models base

Criar/confirmar:
- `app/Models/EarningType.php`
- `app/Models/Earning.php`

Padroes (ver `docs/patterns/models.md`):
- `App\Models\*`
- `$table`, `$fillable`, `casts()` tipado
- Relacionamentos tipados

#### EarningType

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EarningType extends Model
{
    protected $table = 'earning_type';

    protected $fillable = [
        'name', 'short_name', 'label', 'key', 'icon', 'hex_color',
    ];

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }
}
```

#### Earning

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Earning extends Model
{
    protected $table = 'earnings';

    protected $fillable = [
        'consolidated_id',
        'earning_type_id',
        'company_earning_id',
        'date',
        'quantity',
        'net_value',
        'gross_value',
        'tax',
        'imported_with',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'quantity' => 'decimal:8',
            'net_value' => 'decimal:8',
            'gross_value' => 'decimal:8',
            'tax' => 'decimal:8',
        ];
    }

    public function consolidated(): BelongsTo
    {
        return $this->belongsTo(Consolidated::class);
    }

    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class);
    }
}
```

### 1.2 Relacionamentos adicionais

Atualizar `app/Models/Consolidated.php`:

```php
public function earnings(): HasMany
{
    return $this->hasMany(Earning::class);
}
```

Se V8 estiver ativo:
- `CompanyEarning::earnings()` (hasMany)
- `Earning::companyEarning()` (belongsTo)

### 1.3 Seeder de tipos

Criar `database/seeders/EarningTypeSeeder.php`:

Tipos base sugeridos:
- Dividendos
- JCP
- Rendimentos
- Bonificacao
- Outros

Campos sugeridos:
- `name`, `short_name`, `label`, `key`, `icon`, `hex_color`

Adicionar no `DatabaseSeeder`.

---

## Fase 2 - Requests (Validacao)

Criar:
- `app/Http/Requests/Earning/StoreEarningRequest.php`
- `app/Http/Requests/Earning/UpdateEarningRequest.php`

Validacoes recomendadas:

- `consolidated_id`: required, exists, pertence ao usuario logado
- `earning_type_id`: required, exists em `earning_type`
- `company_earning_id`: nullable, exists em `company_earnings` (quando V8 ativo)
- `date`: required, date
- `quantity`: required, numeric, min:0
- `net_value`: required, numeric, min:0
- `gross_value`: nullable, numeric, min:0
- `tax`: nullable, numeric, min:0
- `imported_with`: nullable, in:Manual,Import,Sync

Escopo por usuario:
- validar via `Rule::exists` com `where` em `consolidated.user_id`

---

## Fase 3 - Resources (API Output)

Criar:
- `app/Http/Resources/EarningResource.php`
- `app/Http/Resources/EarningTypeResource.php`

Padrao:
- `snake_case`
- campos principais explicitados
- `earning_type` embutido (id, name, key, label)
- `consolidated` opcional (id, company_ticker_id)

Exemplo `EarningResource`:

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'consolidated_id' => $this->consolidated_id,
        'earning_type_id' => $this->earning_type_id,
        'company_earning_id' => $this->company_earning_id,
        'date' => $this->date,
        'quantity' => $this->quantity,
        'net_value' => $this->net_value,
        'gross_value' => $this->gross_value,
        'tax' => $this->tax,
        'imported_with' => $this->imported_with,
        'earning_type' => new EarningTypeResource($this->whenLoaded('earningType')),
    ];
}
```

---

## Fase 4 - Controllers (API)

Seguir `docs/patterns/controllers.md`:
- BaseController
- `sendResponse` e `sendError`
- Escopo por usuario

### 4.1 EarningTypeController

Arquivo:
- `app/Http/Controllers/Api/EarningTypeController.php`

Endpoints:
- `GET /api/earning-types` (publico, sem auth se desejado)

Implementacao:
- `EarningType::query()->orderBy('name')->get()`
- `EarningTypeResource::collection($types)`

### 4.2 EarningController

Arquivo:
- `app/Http/Controllers/Api/EarningController.php`

Endpoints CRUD:
- `GET /api/earnings`
- `POST /api/earnings`
- `GET /api/earnings/{earning}`
- `PUT/PATCH /api/earnings/{earning}`
- `DELETE /api/earnings/{earning}`

Regras:
- escopo por usuario via `consolidated.user_id`
- eager loading de `earningType`
- order default: `date desc`

Sugestao de filtro:
- `consolidated_id`
- `earning_type_id`
- `from`, `to` (date range)

---

## Fase 5 - Regras de Negocio (Valores)

### 5.1 Regras de valores

- `net_value` e `quantity` sao obrigatorios
- `gross_value` e `tax` sao opcionais
- Se `gross_value` informado e `tax` vazio:
  - `tax = max(gross_value - net_value, 0)`
- Se `tax` informado e `gross_value` vazio:
  - `gross_value = net_value + tax`
- Se ambos informados:
  - validar coerencia: `gross_value >= net_value`
- `quantity` pode ser fracionado (decimal 8)

### 5.2 Origem

Campo `imported_with` define:
- `Manual` para cadastro manual
- `Import` para importacoes
- `Sync` para sincronizacao automatica (futuro)

---

## Fase 6 - Endpoints de Resumo (Analytics)

Criar endpoints de leitura no `EarningController` ou em um controller dedicado:

- `GET /api/earnings/summary?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/earnings/grouped?group=month&from=YYYY-MM-DD&to=YYYY-MM-DD`

### 6.1 Summary

Retorno:
- `total_net`
- `total_gross`
- `total_tax`
- `count`

Query base:

```sql
SELECT
  COUNT(*) as count,
  SUM(net_value) as total_net,
  SUM(gross_value) as total_gross,
  SUM(tax) as total_tax
FROM earnings
WHERE consolidated_id IN (ids do usuario)
  AND date BETWEEN :from AND :to
```

### 6.2 Grouped

Agrupamento por mes:

```sql
SELECT
  DATE_FORMAT(date, '%Y-%m') as period,
  SUM(net_value) as total_net,
  SUM(gross_value) as total_gross,
  SUM(tax) as total_tax,
  COUNT(*) as count
FROM earnings
WHERE consolidated_id IN (ids do usuario)
GROUP BY period
ORDER BY period ASC
```

---

## Fase 7 - Rotas

Adicionar em `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/earning-types', [EarningTypeController::class, 'index']);
    Route::apiResource('earnings', EarningController::class);
    Route::get('/earnings/summary', [EarningController::class, 'summary']);
    Route::get('/earnings/grouped', [EarningController::class, 'grouped']);
});
```

---

## Fase 8 - Testes

Seguir `docs/patterns/tests.md`.

Arquivos:
- `tests/Feature/Api/EarningTest.php`
- `tests/Feature/Api/EarningTypeTest.php`

### 8.1 EarningTypeTest

Casos:
- `test_can_list_earning_types`

### 8.2 EarningTest

Casos minimos:
- `test_can_list_earnings`
- `test_can_create_earning`
- `test_can_show_earning`
- `test_can_update_earning`
- `test_can_delete_earning`
- `test_requires_authentication`
- `test_cannot_access_other_user_earning`
- `test_create_validation_fails`
- `test_summary_endpoint`
- `test_grouped_endpoint`

Usar:
- `RefreshDatabase`
- `setUp()` com `User` + token
- factories de `Consolidated`, `EarningType`, `Earning`

---

## Fase 9 - Documentacao

Atualizar:
- `docs/roadmap-app.md` (se app consumir proventos)
- `docs/tests/README..md` (mencionar os novos testes)
- `docs/patterns/resources.md` (campos e estrutura do resource)

---

## Dependencias e Pontos de Atencao

- V8 cria `company_earnings` e `earning_type` em legacy. Validar se tabela `company_earnings` existe no banco compartilhado.
- Garantir que `consolidated` esteja sempre no escopo do usuario.
- Validar `date` no timezone correto (usar `app.timezone`).

---

## Checklist Final

- [ ] Models + Relacionamentos completos
- [ ] Seeder de tipos criado e registrado
- [ ] Form Requests implementados
- [ ] Resources implementados
- [ ] Controllers + rotas CRUD
- [ ] Endpoints de resumo
- [ ] Testes completos
- [ ] Documentacao atualizada

