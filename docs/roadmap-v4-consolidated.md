# Roadmap V4 - Consolidated (Posicoes Reais)

> Registro de compras e posicoes consolidadas do usuario.

**Dependencia:** V3 completa. **Migrations copiadas** do `datagrana-web` (banco compartilhado).

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migration](#4-migration)
5. [Model](#5-model)
6. [Form Requests](#6-form-requests)
7. [Resource](#7-resource)
8. [Policy](#8-policy)
9. [Controller](#9-controller)
10. [Rotas](#10-rotas)
11. [Casos de Teste](#11-casos-de-teste)
12. [Checklist de Implementacao](#12-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o registro de posicoes consolidadas:

- Registrar compras de ativos por conta
- Calcular saldo atual (balance), lucro (profit) e % de lucro
- Uma posicao por ativo por conta
- Resumo geral das posicoes

**Entregaveis:**
- Tabela `consolidated`
- CRUD de posicoes
- Endpoint de resumo
- Testes automatizados

---

## Regras de Negócio

### Cálculo de Balance (Lucro/Prejuízo)
```php
balance = (current_price * quantity) - (average_price * quantity)
```

### Fechamento de Posição
- Quando `quantity` chega a 0 após venda total
- Registro é **soft deleted** (`deleted_at` é preenchido)
- Mantém histórico para auditoria

### Venda Parcial
- Recalcula `quantity` (subtrai quantidade vendida)
- **Mantém `average_price` original** (preço médio de compra)
- Atualiza `current_price` e `balance`

---

## 2. Dependencias

**Requer:** V3 (Companies) completa

**Tabelas necessarias:**
- `users`
- `accounts`
- `company_tickers`

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── ConsolidatedController.php
│   ├── Requests/
│   │   └── Consolidated/
│   │       ├── StoreConsolidatedRequest.php
│   │       └── UpdateConsolidatedRequest.php
│   └── Resources/
│       └── ConsolidatedResource.php
├── Models/
│   └── Consolidated.php
└── Policies/
    └── ConsolidatedPolicy.php

database/
├── migrations/
│   └── 2025_01_01_000006_create_consolidated_table.php
└── factories/
    └── ConsolidatedFactory.php

tests/
└── Feature/
    └── Consolidated/
        ├── ConsolidatedIndexTest.php
        ├── ConsolidatedStoreTest.php
        ├── ConsolidatedShowTest.php
        ├── ConsolidatedUpdateTest.php
        ├── ConsolidatedDestroyTest.php
        └── ConsolidatedSummaryTest.php
```

---

## 4. Migration

### 4.1 Migration: consolidated

**Arquivo:** `database/migrations/2025_01_01_000006_create_consolidated_table.php`

**Importante:** Copiar do `datagrana-web`. Ja executada no banco compartilhado.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidated', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_ticker_id')->constrained()->cascadeOnDelete();
            $table->decimal('average_purchase_price', 18, 8)->default(0);
            $table->decimal('quantity_current', 18, 8)->default(0);
            $table->decimal('total_purchased', 18, 8)->default(0);
            $table->boolean('closed')->default(false);
            $table->decimal('average_selling_price', 18, 8)->nullable();
            $table->decimal('total_sold', 18, 8)->nullable();
            $table->decimal('quantity_purchased', 18, 8)->nullable();
            $table->decimal('quantity_sold', 18, 8)->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'company_ticker_id']);
            $table->index('closed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated');
    }
};
```

---

## 5. Model

### 5.1 Consolidated Model

**Arquivo:** `app/Models/Consolidated.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consolidated extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'consolidated';

    protected $fillable = [
        'account_id',
        'company_ticker_id',
        'average_purchase_price',
        'quantity_current',
        'total_purchased',
        'closed',
        'average_selling_price',
        'total_sold',
        'quantity_purchased',
        'quantity_sold',
    ];

    protected function casts(): array
    {
        return [
            'average_purchase_price' => 'decimal:8',
            'quantity_current' => 'decimal:8',
            'total_purchased' => 'decimal:8',
            'closed' => 'boolean',
            'average_selling_price' => 'decimal:8',
            'total_sold' => 'decimal:8',
            'quantity_purchased' => 'decimal:8',
            'quantity_sold' => 'decimal:8',
        ];
    }

    protected $appends = [
        'balance',
        'profit',
        'profit_percentage',
    ];

    /**
     * Conta dona da posicao
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Ticker do ativo
     */
    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }

    /**
     * Saldo atual (quantidade * preco atual)
     */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: function () {
                $lastPrice = $this->companyTicker?->last_price ?? 0;
                return round($this->quantity_current * $lastPrice, 2);
            }
        );
    }

    /**
     * Lucro/prejuizo (saldo - total investido)
     */
    protected function profit(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->balance - $this->total_purchased, 2)
        );
    }

    /**
     * Percentual de lucro/prejuizo
     */
    protected function profitPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->total_purchased <= 0) {
                    return 0;
                }
                return round(($this->profit / $this->total_purchased) * 100, 2);
            }
        );
    }

    /**
     * Scope para posicoes abertas
     */
    public function scopeOpen($query)
    {
        return $query->where('closed', false);
    }

    /**
     * Scope para posicoes fechadas
     */
    public function scopeClosed($query)
    {
        return $query->where('closed', true);
    }

    /**
     * Scope para posicoes do usuario
     */
    public function scopeForUser($query, User $user)
    {
        $accountIds = $user->accounts()->pluck('id');
        return $query->whereIn('account_id', $accountIds);
    }
}
```

### 5.2 Atualizar Account Model

**Arquivo:** `app/Models/Account.php` (atualizar metodo)

```php
/**
 * Verifica se a conta tem posicoes ativas
 */
public function hasActivePositions(): bool
{
    return $this->consolidated()->where('closed', false)->exists();
}
```

---

## 6. Form Requests

### 6.1 StoreConsolidatedRequest

**Arquivo:** `app/Http/Requests/Consolidated/StoreConsolidatedRequest.php`

```php
<?php

namespace App\Http\Requests\Consolidated;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsolidatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verifica se a conta pertence ao usuario
        $account = \App\Models\Account::find($this->account_id);
        return $account && $account->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'company_ticker_id' => [
                'required',
                'integer',
                Rule::exists('company_tickers', 'id')->where('status', true),
            ],
            'average_purchase_price' => [
                'required',
                'numeric',
                'min:0.00000001',
            ],
            'quantity_current' => [
                'required',
                'numeric',
                'min:0.00000001',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta e obrigatoria.',
            'account_id.exists' => 'Conta nao encontrada ou nao pertence a voce.',
            'company_ticker_id.required' => 'O ativo e obrigatorio.',
            'company_ticker_id.exists' => 'Ativo nao encontrado ou inativo.',
            'average_purchase_price.required' => 'O preco medio e obrigatorio.',
            'average_purchase_price.min' => 'O preco medio deve ser maior que zero.',
            'quantity_current.required' => 'A quantidade e obrigatoria.',
            'quantity_current.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
```

### 6.2 UpdateConsolidatedRequest

**Arquivo:** `app/Http/Requests/Consolidated/UpdateConsolidatedRequest.php`

```php
<?php

namespace App\Http\Requests\Consolidated;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsolidatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy cuida da autorizacao
    }

    public function rules(): array
    {
        return [
            'average_purchase_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.00000001',
            ],
            'quantity_current' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'closed' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'average_purchase_price.min' => 'O preco medio deve ser maior que zero.',
            'quantity_current.min' => 'A quantidade nao pode ser negativa.',
        ];
    }
}
```

---

## 7. Resource

### 7.1 ConsolidatedResource

**Arquivo:** `app/Http/Resources/ConsolidatedResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsolidatedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'company_ticker_id' => $this->company_ticker_id,
            'average_purchase_price' => (string) $this->average_purchase_price,
            'quantity_current' => (string) $this->quantity_current,
            'total_purchased' => (string) $this->total_purchased,
            'closed' => $this->closed,
            'average_selling_price' => $this->average_selling_price ? (string) $this->average_selling_price : null,
            'total_sold' => $this->total_sold ? (string) $this->total_sold : null,
            'quantity_purchased' => $this->quantity_purchased ? (string) $this->quantity_purchased : null,
            'quantity_sold' => $this->quantity_sold ? (string) $this->quantity_sold : null,

            // Campos calculados
            'balance' => (string) $this->balance,
            'profit' => (string) $this->profit,
            'profit_percentage' => (string) $this->profit_percentage,

            // Relacionamentos
            'account' => new AccountResource($this->whenLoaded('account')),
            'company_ticker' => new CompanyTickerResource($this->whenLoaded('companyTicker')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## 8. Policy

### 8.1 ConsolidatedPolicy

**Arquivo:** `app/Policies/ConsolidatedPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Consolidated;
use App\Models\User;

class ConsolidatedPolicy
{
    /**
     * Usuario pode ver a posicao?
     */
    public function view(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }

    /**
     * Usuario pode atualizar a posicao?
     */
    public function update(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }

    /**
     * Usuario pode deletar a posicao?
     */
    public function delete(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }
}
```

### 8.2 Registrar Policy

**Arquivo:** `app/Providers/AppServiceProvider.php` (adicionar)

```php
use App\Models\Consolidated;
use App\Policies\ConsolidatedPolicy;

// No metodo boot():
Gate::policy(Consolidated::class, ConsolidatedPolicy::class);
```

---

## 9. Controller

### 9.1 ConsolidatedController

**Arquivo:** `app/Http/Controllers/Api/ConsolidatedController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Consolidated\StoreConsolidatedRequest;
use App\Http\Requests\Consolidated\UpdateConsolidatedRequest;
use App\Http\Resources\ConsolidatedResource;
use App\Models\Consolidated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidatedController extends BaseController
{
    /**
     * GET /api/consolidated
     *
     * Lista posicoes do usuario
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'closed' => ['nullable', 'boolean'],
        ]);

        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->when($request->account_id, fn($q, $accountId) =>
                $q->where('account_id', $accountId)
            )
            ->when($request->has('closed'), fn($q) =>
                $q->where('closed', $request->boolean('closed'))
            )
            ->with([
                'companyTicker.company.companyCategory',
                'account.bank',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(ConsolidatedResource::collection($consolidated));
    }

    /**
     * POST /api/consolidated
     *
     * Registra nova posicao
     */
    public function store(StoreConsolidatedRequest $request): JsonResponse
    {
        // Verifica se ja existe posicao para este ativo na conta
        $existing = Consolidated::where('account_id', $request->account_id)
            ->where('company_ticker_id', $request->company_ticker_id)
            ->first();

        if ($existing) {
            return $this->sendError(
                'Ja existe uma posicao para este ativo nesta conta. Atualize a posicao existente.',
                [],
                409
            );
        }

        $data = $request->validated();
        $data['quantity_purchased'] = $data['quantity_current'];
        $data['total_purchased'] = round($data['quantity_current'] * $data['average_purchase_price'], 8);

        $consolidated = Consolidated::create($data);

        return $this->sendResponse(
            new ConsolidatedResource(
                $consolidated->load('companyTicker.company.companyCategory', 'account.bank')
            ),
            'Posicao registrada com sucesso.'
        );
    }

    /**
     * GET /api/consolidated/{consolidated}
     *
     * Exibe detalhes de uma posicao
     */
    public function show(Consolidated $consolidated): JsonResponse
    {
        $this->authorize('view', $consolidated);

        return $this->sendResponse(
            new ConsolidatedResource(
                $consolidated->load('companyTicker.company.companyCategory', 'account.bank')
            )
        );
    }

    /**
     * PUT /api/consolidated/{consolidated}
     *
     * Atualiza uma posicao
     */
    public function update(UpdateConsolidatedRequest $request, Consolidated $consolidated): JsonResponse
    {
        $this->authorize('update', $consolidated);

        $data = $request->validated();

        // Recalcula total_purchased se necessario
        if (isset($data['quantity_current']) || isset($data['average_purchase_price'])) {
            $quantity = $data['quantity_current'] ?? $consolidated->quantity_current;
            $price = $data['average_purchase_price'] ?? $consolidated->average_purchase_price;
            $data['total_purchased'] = round($quantity * $price, 8);
        }

        $consolidated->update($data);

        return $this->sendResponse(
            new ConsolidatedResource(
                $consolidated->fresh()->load('companyTicker.company.companyCategory', 'account.bank')
            ),
            'Posicao atualizada com sucesso.'
        );
    }

    /**
     * DELETE /api/consolidated/{consolidated}
     *
     * Remove uma posicao
     */
    public function destroy(Consolidated $consolidated): JsonResponse
    {
        $this->authorize('delete', $consolidated);

        $consolidated->delete();

        return $this->sendResponse([], 'Posicao removida com sucesso.');
    }

    /**
     * GET /api/consolidated/summary
     *
     * Resumo das posicoes do usuario
     */
    public function summary(Request $request): JsonResponse
    {
        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->open()
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        $totalInvested = $consolidated->sum('total_purchased');
        $totalCurrent = $consolidated->sum('balance');
        $totalProfit = $totalCurrent - $totalInvested;
        $profitPercentage = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        $byCategory = $consolidated->groupBy(function ($item) {
            return $item->companyTicker->company->companyCategory->name;
        })->map(function ($items, $categoryName) {
            $invested = $items->sum('total_purchased');
            $current = $items->sum('balance');
            $profit = $current - $invested;

            return [
                'category' => $categoryName,
                'count' => $items->count(),
                'invested' => round($invested, 2),
                'current' => round($current, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $invested > 0 ? round(($profit / $invested) * 100, 2) : 0,
            ];
        })->values();

        $byAccount = $consolidated->groupBy('account_id')->map(function ($items) {
            $account = $items->first()->account;
            $invested = $items->sum('total_purchased');
            $current = $items->sum('balance');
            $profit = $current - $invested;

            return [
                'account_id' => $account->id,
                'account_name' => $account->nickname ?? $account->account,
                'bank' => $account->bank?->nickname ?? $account->bank?->name,
                'count' => $items->count(),
                'invested' => round($invested, 2),
                'current' => round($current, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $invested > 0 ? round(($profit / $invested) * 100, 2) : 0,
            ];
        })->values();

        return $this->sendResponse([
            'total_invested' => round($totalInvested, 2),
            'total_current' => round($totalCurrent, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_percentage' => round($profitPercentage, 2),
            'assets_count' => $consolidated->count(),
            'by_category' => $byCategory,
            'by_account' => $byAccount,
        ]);
    }
}
```

---

## 10. Rotas

### 10.1 Atualizar routes/api.php

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ConsolidatedController;
use Illuminate\Support\Facades\Route;

// ... rotas anteriores ...

Route::middleware('auth:sanctum')->group(function () {
    // ... outras rotas ...

    // Consolidated (V4)
    Route::get('/consolidated/summary', [ConsolidatedController::class, 'summary'])
        ->name('consolidated.summary');
    Route::apiResource('consolidated', ConsolidatedController::class);
});
```

---

## 11. Casos de Teste

### 11.1 Factory

**Arquivo:** `database/factories/ConsolidatedFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CompanyTicker;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsolidatedFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(8, 1, 1000);
        $price = fake()->randomFloat(8, 10, 100);

        return [
            'account_id' => Account::factory(),
            'company_ticker_id' => CompanyTicker::factory(),
            'average_purchase_price' => $price,
            'quantity_current' => $quantity,
            'total_purchased' => $quantity * $price,
            'closed' => false,
            'quantity_purchased' => $quantity,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed' => true,
            'quantity_current' => 0,
            'average_selling_price' => fake()->randomFloat(8, 10, 150),
            'total_sold' => $attributes['total_purchased'] * 1.1,
            'quantity_sold' => $attributes['quantity_purchased'],
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }

    public function forTicker(CompanyTicker $ticker): static
    {
        return $this->state(fn (array $attributes) => [
            'company_ticker_id' => $ticker->id,
        ]);
    }
}
```

### 11.2 ConsolidatedIndexTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedIndexTest.php`

```php
<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_list_own_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(3)->forAccount($account)->create();
        Consolidated::factory()->count(2)->create(); // Outras posicoes

        $response = $this->getJson('/api/consolidated', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function test_can_filter_by_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account1 = Account::factory()->create(['user_id' => $auth['user']->id]);
        $account2 = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(2)->forAccount($account1)->create();
        Consolidated::factory()->count(3)->forAccount($account2)->create();

        $response = $this->getJson(
            "/api/consolidated?account_id={$account1->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * @test
     */
    public function test_can_filter_by_closed_status(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(2)->forAccount($account)->create(['closed' => false]);
        Consolidated::factory()->count(1)->forAccount($account)->create(['closed' => true]);

        $response = $this->getJson(
            '/api/consolidated?closed=false',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * @test
     */
    public function test_cannot_list_positions_without_authentication(): void
    {
        $response = $this->getJson('/api/consolidated');

        $response->assertStatus(401);
    }
}
```

### 11.3 ConsolidatedStoreTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedStoreTest.php`

```php
<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_create_position(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson('/api/consolidated', [
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 35.50,
            'quantity_current' => 100,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Posicao registrada com sucesso.',
            ])
            ->assertJsonPath('data.average_purchase_price', '35.50000000')
            ->assertJsonPath('data.quantity_current', '100.00000000');

        $this->assertDatabaseHas('consolidated', [
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
        ]);
    }

    /**
     * @test
     */
    public function test_cannot_create_duplicate_position(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $ticker = CompanyTicker::factory()->create();

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
        ]);

        $response = $this->postJson('/api/consolidated', [
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 40.00,
            'quantity_current' => 50,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_create_position_for_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson('/api/consolidated', [
            'account_id' => $otherAccount->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 35.50,
            'quantity_current' => 100,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function test_calculates_total_purchased(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson('/api/consolidated', [
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 10.00,
            'quantity_current' => 100,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $this->assertDatabaseHas('consolidated', [
            'account_id' => $account->id,
            'total_purchased' => 1000.00000000,
        ]);
    }
}
```

### 11.4 ConsolidatedSummaryTest

**Arquivo:** `tests/Feature/Consolidated/ConsolidatedSummaryTest.php`

```php
<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_get_summary(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'last_price' => 50.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 40.00,
            'quantity_current' => 100,
            'total_purchased' => 4000.00,
            'closed' => false,
        ]);

        $response = $this->getJson('/api/consolidated/summary', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_invested',
                    'total_current',
                    'total_profit',
                    'profit_percentage',
                    'assets_count',
                    'by_category',
                    'by_account',
                ],
            ])
            ->assertJsonPath('data.total_invested', 4000.00)
            ->assertJsonPath('data.total_current', 5000.00)
            ->assertJsonPath('data.total_profit', 1000.00)
            ->assertJsonPath('data.assets_count', 1);
    }

    /**
     * @test
     */
    public function test_summary_excludes_closed_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->forAccount($account)->create(['closed' => false]);
        Consolidated::factory()->forAccount($account)->create(['closed' => true]);

        $response = $this->getJson('/api/consolidated/summary', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.assets_count', 1);
    }

    /**
     * @test
     */
    public function test_summary_groups_by_category(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $categoryAcoes = CompanyCategory::factory()->create(['name' => 'Acoes']);
        $categoryFiis = CompanyCategory::factory()->create(['name' => 'FIIs']);

        $companyAcao = Company::factory()->create(['company_category_id' => $categoryAcoes->id]);
        $companyFii = Company::factory()->create(['company_category_id' => $categoryFiis->id]);

        $tickerAcao = CompanyTicker::factory()->create(['company_id' => $companyAcao->id]);
        $tickerFii = CompanyTicker::factory()->create(['company_id' => $companyFii->id]);

        Consolidated::factory()->forAccount($account)->forTicker($tickerAcao)->create();
        Consolidated::factory()->forAccount($account)->forTicker($tickerFii)->create();

        $response = $this->getJson('/api/consolidated/summary', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.by_category');
    }
}
```

---

## 12. Checklist de Implementacao

### 12.1 Database

- [ ] Criar migration `consolidated`
- [ ] Rodar `php artisan migrate`
- [ ] Criar `ConsolidatedFactory`

### 12.2 Models

- [ ] Criar `Consolidated` model
- [ ] Atualizar `Account` model (hasActivePositions)

### 12.3 Backend

- [ ] Criar `StoreConsolidatedRequest`
- [ ] Criar `UpdateConsolidatedRequest`
- [ ] Criar `ConsolidatedResource`
- [ ] Criar `ConsolidatedPolicy`
- [ ] Registrar Policy
- [ ] Criar `ConsolidatedController`
- [ ] Configurar rotas

### 12.4 Testes

- [ ] Criar `ConsolidatedIndexTest`
- [ ] Criar `ConsolidatedStoreTest`
- [ ] Criar `ConsolidatedShowTest`
- [ ] Criar `ConsolidatedUpdateTest`
- [ ] Criar `ConsolidatedDestroyTest`
- [ ] Criar `ConsolidatedSummaryTest`
- [ ] Rodar `php artisan test` - todos passando

### 12.5 Validacao Final

- [ ] Testar `GET /api/consolidated`
- [ ] Testar `POST /api/consolidated`
- [ ] Testar `GET /api/consolidated/{id}`
- [ ] Testar `PUT /api/consolidated/{id}`
- [ ] Testar `DELETE /api/consolidated/{id}`
- [ ] Testar `GET /api/consolidated/summary`

---

## Endpoints da V4

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/consolidated` | Sim | Lista posicoes |
| POST | `/api/consolidated` | Sim | Registra posicao |
| GET | `/api/consolidated/{id}` | Sim | Detalhes da posicao |
| PUT | `/api/consolidated/{id}` | Sim | Atualiza posicao |
| DELETE | `/api/consolidated/{id}` | Sim | Remove posicao |
| GET | `/api/consolidated/summary` | Sim | Resumo geral |

---

## Proxima Fase

Apos completar a V4, prosseguir para:
- **V5 - Portfolio**: Carteiras ideais e composicoes
