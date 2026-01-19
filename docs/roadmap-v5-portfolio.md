# Roadmap V5 - Portfolio (Carteiras Ideais)

> Carteiras de investimento e composicoes com alocacao percentual.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Form Requests](#6-form-requests)
7. [Resources](#7-resources)
8. [Policy](#8-policy)
9. [Controllers](#9-controllers)
10. [Rotas](#10-rotas)
11. [Casos de Teste](#11-casos-de-teste)
12. [Checklist de Implementacao](#12-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o sistema de carteiras ideais:

- CRUD de portfolios
- Composicoes com percentual de alocacao
- Historico de ativos removidos
- Um ativo por portfolio (unicidade)

**Entregaveis:**
- Tabelas `portfolios`, `portfolio_compositions`, `portfolio_composition_histories`
- CRUD completo de portfolio e composicoes
- Testes automatizados

---

## 2. Dependencias

**Requer:** V4 (Consolidated) completa

**Tabelas necessarias:**
- `users`
- `company_tickers`
- `consolidated`

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── PortfolioController.php
│   │       └── CompositionController.php
│   ├── Requests/
│   │   └── Portfolio/
│   │       ├── StorePortfolioRequest.php
│   │       ├── UpdatePortfolioRequest.php
│   │       ├── StoreCompositionRequest.php
│   │       └── UpdateCompositionRequest.php
│   └── Resources/
│       ├── PortfolioResource.php
│       ├── CompositionResource.php
│       └── CompositionHistoryResource.php
├── Models/
│   ├── Portfolio.php
│   ├── Composition.php
│   └── CompositionHistory.php
└── Policies/
    └── PortfolioPolicy.php

database/
├── migrations/
│   ├── 2025_01_01_000007_create_portfolios_table.php
│   ├── 2025_01_01_000008_create_portfolio_compositions_table.php
│   └── 2025_01_01_000009_create_portfolio_composition_histories_table.php
└── factories/
    ├── PortfolioFactory.php
    ├── CompositionFactory.php
    └── CompositionHistoryFactory.php

tests/
└── Feature/
    └── Portfolio/
        ├── PortfolioIndexTest.php
        ├── PortfolioStoreTest.php
        ├── PortfolioUpdateTest.php
        ├── PortfolioDestroyTest.php
        ├── CompositionStoreTest.php
        ├── CompositionUpdateTest.php
        └── CompositionDestroyTest.php
```

---

## 4. Migrations

### 4.1 Migration: portfolios

**Arquivo:** `database/migrations/2025_01_01_000007_create_portfolios_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('month_value', 12, 2)->default(0);
            $table->decimal('target_value', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
```

### 4.2 Migration: portfolio_compositions

**Arquivo:** `database/migrations/2025_01_01_000008_create_portfolio_compositions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_ticker_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 10, 2);
            $table->timestamps();

            $table->unique(['portfolio_id', 'company_ticker_id'], 'unique_portfolio_ticker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_compositions');
    }
};
```

### 4.3 Migration: portfolio_composition_histories

**Arquivo:** `database/migrations/2025_01_01_000009_create_portfolio_composition_histories_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_composition_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_ticker_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 10, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_composition_histories');
    }
};
```

---

## 5. Models

### 5.1 Portfolio Model

**Arquivo:** `app/Models/Portfolio.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Portfolio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'month_value',
        'target_value',
    ];

    protected function casts(): array
    {
        return [
            'month_value' => 'decimal:2',
            'target_value' => 'decimal:2',
        ];
    }

    protected $appends = [
        'total_percentage',
        'compositions_count',
    ];

    /**
     * Usuario dono do portfolio
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Composicoes do portfolio
     */
    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }

    /**
     * Historico de composicoes removidas
     */
    public function compositionHistories(): HasMany
    {
        return $this->hasMany(CompositionHistory::class);
    }

    /**
     * Total de porcentagem alocada
     */
    protected function totalPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->compositions()->sum('percentage'), 2)
        );
    }

    /**
     * Quantidade de ativos na carteira
     */
    protected function compositionsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->compositions()->count()
        );
    }

    /**
     * Verifica se a carteira esta completa (100%)
     */
    public function isComplete(): bool
    {
        return $this->total_percentage >= 100;
    }

    /**
     * Porcentagem restante para completar 100%
     */
    public function getRemainingPercentage(): float
    {
        return max(0, 100 - $this->total_percentage);
    }
}
```

### 5.2 Composition Model

**Arquivo:** `app/Models/Composition.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Composition extends Model
{
    use HasFactory;

    protected $table = 'portfolio_compositions';

    protected $fillable = [
        'portfolio_id',
        'company_ticker_id',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * Portfolio desta composicao
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Ticker do ativo
     */
    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}
```

### 5.3 CompositionHistory Model

**Arquivo:** `app/Models/CompositionHistory.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompositionHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'portfolio_composition_histories';

    protected $fillable = [
        'portfolio_id',
        'company_ticker_id',
        'percentage',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * Portfolio desta composicao historica
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Ticker do ativo
     */
    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}
```

### 5.4 Atualizar User Model

**Arquivo:** `app/Models/User.php` (adicionar relacionamento)

```php
/**
 * Portfolios do usuario
 */
public function portfolios(): HasMany
{
    return $this->hasMany(Portfolio::class);
}
```

---

## 6. Form Requests

### 6.1 StorePortfolioRequest

**Arquivo:** `app/Http/Requests/Portfolio/StorePortfolioRequest.php`

```php
<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class StorePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'month_value' => ['nullable', 'numeric', 'min:0'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do portfolio e obrigatorio.',
            'name.max' => 'O nome deve ter no maximo 80 caracteres.',
            'month_value.min' => 'O valor mensal nao pode ser negativo.',
            'target_value.min' => 'O valor objetivo nao pode ser negativo.',
        ];
    }
}
```

### 6.2 UpdatePortfolioRequest

**Arquivo:** `app/Http/Requests/Portfolio/UpdatePortfolioRequest.php`

```php
<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'month_value' => ['nullable', 'numeric', 'min:0'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

### 6.3 StoreCompositionRequest

**Arquivo:** `app/Http/Requests/Portfolio/StoreCompositionRequest.php`

```php
<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy cuida da autorizacao
    }

    public function rules(): array
    {
        return [
            'compositions' => ['required', 'array', 'min:1'],
            'compositions.*.company_ticker_id' => [
                'required',
                'integer',
                Rule::exists('company_tickers', 'id')->where('status', true),
            ],
            'compositions.*.percentage' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'compositions.required' => 'Informe pelo menos um ativo.',
            'compositions.*.company_ticker_id.required' => 'O ativo e obrigatorio.',
            'compositions.*.company_ticker_id.exists' => 'Ativo nao encontrado ou inativo.',
            'compositions.*.percentage.required' => 'A porcentagem e obrigatoria.',
            'compositions.*.percentage.min' => 'A porcentagem deve ser maior que zero.',
            'compositions.*.percentage.max' => 'A porcentagem nao pode ser maior que 100%.',
        ];
    }
}
```

### 6.4 UpdateCompositionRequest

**Arquivo:** `app/Http/Requests/Portfolio/UpdateCompositionRequest.php`

```php
<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'percentage.required' => 'A porcentagem e obrigatoria.',
            'percentage.min' => 'A porcentagem deve ser maior que zero.',
            'percentage.max' => 'A porcentagem nao pode ser maior que 100%.',
        ];
    }
}
```

---

## 7. Resources

### 7.1 PortfolioResource

**Arquivo:** `app/Http/Resources/PortfolioResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'month_value' => (string) $this->month_value,
            'target_value' => (string) $this->target_value,
            'total_percentage' => (string) $this->total_percentage,
            'compositions_count' => $this->compositions_count,
            'is_complete' => $this->isComplete(),
            'remaining_percentage' => (string) $this->getRemainingPercentage(),

            'compositions' => CompositionResource::collection($this->whenLoaded('compositions')),
            'composition_histories' => CompositionHistoryResource::collection($this->whenLoaded('compositionHistories')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### 7.2 CompositionResource

**Arquivo:** `app/Http/Resources/CompositionResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'portfolio_id' => $this->portfolio_id,
            'company_ticker_id' => $this->company_ticker_id,
            'percentage' => (string) $this->percentage,

            'company_ticker' => new CompanyTickerResource($this->whenLoaded('companyTicker')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### 7.3 CompositionHistoryResource

**Arquivo:** `app/Http/Resources/CompositionHistoryResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'portfolio_id' => $this->portfolio_id,
            'company_ticker_id' => $this->company_ticker_id,
            'percentage' => $this->percentage ? (string) $this->percentage : null,
            'reason' => $this->reason,

            'company_ticker' => new CompanyTickerResource($this->whenLoaded('companyTicker')),

            'created_at' => $this->created_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
```

---

## 8. Policy

### 8.1 PortfolioPolicy

**Arquivo:** `app/Policies/PortfolioPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    /**
     * Usuario pode ver o portfolio?
     */
    public function view(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /**
     * Usuario pode atualizar o portfolio?
     */
    public function update(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /**
     * Usuario pode deletar o portfolio?
     */
    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }
}
```

### 8.2 Registrar Policy

**Arquivo:** `app/Providers/AppServiceProvider.php` (adicionar)

```php
use App\Models\Portfolio;
use App\Policies\PortfolioPolicy;

// No metodo boot():
Gate::policy(Portfolio::class, PortfolioPolicy::class);
```

---

## 9. Controllers

### 9.1 PortfolioController

**Arquivo:** `app/Http/Controllers/Api/PortfolioController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends BaseController
{
    /**
     * GET /api/portfolios
     *
     * Lista portfolios do usuario
     */
    public function index(Request $request): JsonResponse
    {
        $portfolios = $request->user()->portfolios()
            ->when($request->search, fn($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->with(['compositions.companyTicker.company.companyCategory'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(PortfolioResource::collection($portfolios));
    }

    /**
     * POST /api/portfolios
     *
     * Cria novo portfolio
     */
    public function store(StorePortfolioRequest $request): JsonResponse
    {
        $portfolio = $request->user()->portfolios()->create($request->validated());

        return $this->sendResponse(
            new PortfolioResource($portfolio),
            'Portfolio criado com sucesso.'
        );
    }

    /**
     * GET /api/portfolios/{portfolio}
     *
     * Exibe detalhes de um portfolio
     */
    public function show(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('view', $portfolio);

        $portfolio->load([
            'compositions.companyTicker.company.companyCategory',
            'compositionHistories.companyTicker.company.companyCategory',
        ]);

        return $this->sendResponse(new PortfolioResource($portfolio));
    }

    /**
     * PUT /api/portfolios/{portfolio}
     *
     * Atualiza um portfolio
     */
    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($request->validated());

        return $this->sendResponse(
            new PortfolioResource($portfolio->fresh()),
            'Portfolio atualizado com sucesso.'
        );
    }

    /**
     * DELETE /api/portfolios/{portfolio}
     *
     * Remove um portfolio (soft delete)
     */
    public function destroy(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return $this->sendResponse([], 'Portfolio removido com sucesso.');
    }
}
```

### 9.2 CompositionController

**Arquivo:** `app/Http/Controllers/Api/CompositionController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StoreCompositionRequest;
use App\Http\Requests\Portfolio\UpdateCompositionRequest;
use App\Http\Resources\CompositionResource;
use App\Models\Composition;
use App\Models\CompositionHistory;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompositionController extends BaseController
{
    /**
     * POST /api/portfolios/{portfolio}/compositions
     *
     * Adiciona ativos ao portfolio
     */
    public function store(StoreCompositionRequest $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $created = [];
        $skipped = [];

        foreach ($request->compositions as $item) {
            // Verifica se ja existe
            $exists = $portfolio->compositions()
                ->where('company_ticker_id', $item['company_ticker_id'])
                ->exists();

            if ($exists) {
                $skipped[] = $item['company_ticker_id'];
                continue;
            }

            $composition = $portfolio->compositions()->create([
                'company_ticker_id' => $item['company_ticker_id'],
                'percentage' => $item['percentage'],
            ]);

            $created[] = $composition;
        }

        $compositions = collect($created)->load('companyTicker.company.companyCategory');

        $message = count($created) > 0
            ? count($created) . ' ativo(s) adicionado(s) ao portfolio.'
            : 'Nenhum ativo adicionado (ja existentes).';

        if (count($skipped) > 0) {
            $message .= ' ' . count($skipped) . ' ativo(s) ignorado(s) por ja existirem.';
        }

        return $this->sendResponse(
            CompositionResource::collection($compositions),
            $message
        );
    }

    /**
     * PUT /api/compositions/{composition}
     *
     * Atualiza porcentagem de um ativo
     */
    public function update(UpdateCompositionRequest $request, Composition $composition): JsonResponse
    {
        $this->authorize('update', $composition->portfolio);

        $composition->update([
            'percentage' => $request->percentage,
        ]);

        return $this->sendResponse(
            new CompositionResource(
                $composition->fresh()->load('companyTicker.company.companyCategory')
            ),
            'Porcentagem atualizada com sucesso.'
        );
    }

    /**
     * PUT /api/compositions/batch
     *
     * Atualiza multiplas composicoes
     */
    public function updateBatch(Request $request): JsonResponse
    {
        $request->validate([
            'compositions' => ['required', 'array', 'min:1'],
            'compositions.*.id' => ['required', 'integer', 'exists:portfolio_compositions,id'],
            'compositions.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $updated = [];

        foreach ($request->compositions as $item) {
            $composition = Composition::find($item['id']);
            $this->authorize('update', $composition->portfolio);

            $composition->update(['percentage' => $item['percentage']]);
            $updated[] = $composition->fresh();
        }

        return $this->sendResponse(
            CompositionResource::collection(collect($updated)->load('companyTicker.company.companyCategory')),
            'Composicoes atualizadas com sucesso.'
        );
    }

    /**
     * DELETE /api/compositions/{composition}
     *
     * Remove ativo do portfolio (opcionalmente salva historico)
     */
    public function destroy(Request $request, Composition $composition): JsonResponse
    {
        $this->authorize('update', $composition->portfolio);

        $request->validate([
            'save_to_history' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Salva no historico se solicitado
        if ($request->boolean('save_to_history')) {
            CompositionHistory::create([
                'portfolio_id' => $composition->portfolio_id,
                'company_ticker_id' => $composition->company_ticker_id,
                'percentage' => $composition->percentage,
                'reason' => $request->reason,
            ]);
        }

        $composition->delete();

        return $this->sendResponse([], 'Ativo removido do portfolio.');
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
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\CompositionController;
use Illuminate\Support\Facades\Route;

// ... rotas anteriores ...

Route::middleware('auth:sanctum')->group(function () {
    // ... outras rotas ...

    // Portfolios (V5)
    Route::apiResource('portfolios', PortfolioController::class);

    // Compositions (V5)
    Route::post('/portfolios/{portfolio}/compositions', [CompositionController::class, 'store'])
        ->name('compositions.store');
    Route::put('/compositions/batch', [CompositionController::class, 'updateBatch'])
        ->name('compositions.batch');
    Route::put('/compositions/{composition}', [CompositionController::class, 'update'])
        ->name('compositions.update');
    Route::delete('/compositions/{composition}', [CompositionController::class, 'destroy'])
        ->name('compositions.destroy');
});
```

---

## 11. Casos de Teste

### 11.1 Factories

**Arquivo:** `database/factories/PortfolioFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PortfolioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'month_value' => fake()->randomFloat(2, 100, 5000),
            'target_value' => fake()->randomFloat(2, 10000, 100000),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
```

**Arquivo:** `database/factories/CompositionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CompanyTicker;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'company_ticker_id' => CompanyTicker::factory(),
            'percentage' => fake()->randomFloat(2, 1, 30),
        ];
    }

    public function forPortfolio(Portfolio $portfolio): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_id' => $portfolio->id,
        ]);
    }
}
```

### 11.2 PortfolioIndexTest

**Arquivo:** `tests/Feature/Portfolio/PortfolioIndexTest.php`

```php
<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_list_own_portfolios(): void
    {
        $auth = $this->createAuthenticatedUser();

        Portfolio::factory()->count(3)->forUser($auth['user'])->create();
        Portfolio::factory()->count(2)->create(); // Outros portfolios

        $response = $this->getJson('/api/portfolios', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function test_can_search_portfolios_by_name(): void
    {
        $auth = $this->createAuthenticatedUser();

        Portfolio::factory()->forUser($auth['user'])->create(['name' => 'Carteira Dividendos']);
        Portfolio::factory()->forUser($auth['user'])->create(['name' => 'Carteira Growth']);

        $response = $this->getJson('/api/portfolios?search=Dividendos', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Carteira Dividendos');
    }

    /**
     * @test
     */
    public function test_cannot_list_portfolios_without_authentication(): void
    {
        $response = $this->getJson('/api/portfolios');

        $response->assertStatus(401);
    }
}
```

### 11.3 PortfolioStoreTest

**Arquivo:** `tests/Feature/Portfolio/PortfolioStoreTest.php`

```php
<?php

namespace Tests\Feature\Portfolio;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_create_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Meu Portfolio',
            'month_value' => 1000.00,
            'target_value' => 50000.00,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio criado com sucesso.',
            ])
            ->assertJsonPath('data.name', 'Meu Portfolio');

        $this->assertDatabaseHas('portfolios', [
            'user_id' => $auth['user']->id,
            'name' => 'Meu Portfolio',
        ]);
    }

    /**
     * @test
     */
    public function test_name_is_required(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'month_value' => 1000.00,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * @test
     */
    public function test_name_max_length(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => str_repeat('a', 81),
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * @test
     */
    public function test_values_cannot_be_negative(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Test',
            'month_value' => -100,
            'target_value' => -1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month_value', 'target_value']);
    }
}
```

### 11.4 CompositionStoreTest

**Arquivo:** `tests/Feature/Portfolio/CompositionStoreTest.php`

```php
<?php

namespace Tests\Feature\Portfolio;

use App\Models\CompanyTicker;
use App\Models\Composition;
use App\Models\Portfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositionStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_add_composition_to_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'company_ticker_id' => $ticker->id,
                    'percentage' => 25.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('portfolio_compositions', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);
    }

    /**
     * @test
     */
    public function test_cannot_add_duplicate_ticker(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
        ]);

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'company_ticker_id' => $ticker->id,
                    'percentage' => 25.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        // Deve ter apenas 1 composicao (a original)
        $this->assertDatabaseCount('portfolio_compositions', 1);
    }

    /**
     * @test
     */
    public function test_cannot_add_to_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherPortfolio = Portfolio::factory()->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$otherPortfolio->id}/compositions", [
            'compositions' => [
                [
                    'company_ticker_id' => $ticker->id,
                    'percentage' => 25.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function test_percentage_must_be_valid(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'company_ticker_id' => $ticker->id,
                    'percentage' => 150.00, // > 100
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['compositions.0.percentage']);
    }
}
```

### 11.5 CompositionDestroyTest

**Arquivo:** `tests/Feature/Portfolio/CompositionDestroyTest.php`

```php
<?php

namespace Tests\Feature\Portfolio;

use App\Models\Composition;
use App\Models\Portfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositionDestroyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_remove_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create();

        $response = $this->deleteJson(
            "/api/compositions/{$composition->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ativo removido do portfolio.',
            ]);

        $this->assertDatabaseMissing('portfolio_compositions', [
            'id' => $composition->id,
        ]);
    }

    /**
     * @test
     */
    public function test_can_save_to_history_on_remove(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create([
            'percentage' => 15.00,
        ]);

        $response = $this->deleteJson("/api/compositions/{$composition->id}", [
            'save_to_history' => true,
            'reason' => 'Empresa nao paga mais dividendos',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $this->assertDatabaseHas('portfolio_composition_histories', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $composition->company_ticker_id,
            'percentage' => 15.00,
            'reason' => 'Empresa nao paga mais dividendos',
        ]);
    }

    /**
     * @test
     */
    public function test_cannot_remove_other_user_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherPortfolio = Portfolio::factory()->create();
        $composition = Composition::factory()->forPortfolio($otherPortfolio)->create();

        $response = $this->deleteJson(
            "/api/compositions/{$composition->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }
}
```

---

## 12. Checklist de Implementacao

### 12.1 Database

- [ ] Criar migration `portfolios`
- [ ] Criar migration `portfolio_compositions`
- [ ] Criar migration `portfolio_composition_histories`
- [ ] Rodar `php artisan migrate`
- [ ] Criar `PortfolioFactory`
- [ ] Criar `CompositionFactory`
- [ ] Criar `CompositionHistoryFactory`

### 12.2 Models

- [ ] Criar `Portfolio` model
- [ ] Criar `Composition` model
- [ ] Criar `CompositionHistory` model
- [ ] Atualizar `User` model (relacionamento)

### 12.3 Backend

- [ ] Criar Form Requests (Store/Update Portfolio, Store/Update Composition)
- [ ] Criar `PortfolioResource`
- [ ] Criar `CompositionResource`
- [ ] Criar `CompositionHistoryResource`
- [ ] Criar `PortfolioPolicy`
- [ ] Registrar Policy
- [ ] Criar `PortfolioController`
- [ ] Criar `CompositionController`
- [ ] Configurar rotas

### 12.4 Testes

- [ ] Criar `PortfolioIndexTest`
- [ ] Criar `PortfolioStoreTest`
- [ ] Criar `PortfolioUpdateTest`
- [ ] Criar `PortfolioDestroyTest`
- [ ] Criar `CompositionStoreTest`
- [ ] Criar `CompositionUpdateTest`
- [ ] Criar `CompositionDestroyTest`
- [ ] Rodar `php artisan test` - todos passando

### 12.5 Validacao Final

- [ ] Testar `GET /api/portfolios`
- [ ] Testar `POST /api/portfolios`
- [ ] Testar `GET /api/portfolios/{id}`
- [ ] Testar `PUT /api/portfolios/{id}`
- [ ] Testar `DELETE /api/portfolios/{id}`
- [ ] Testar `POST /api/portfolios/{id}/compositions`
- [ ] Testar `PUT /api/compositions/{id}`
- [ ] Testar `PUT /api/compositions/batch`
- [ ] Testar `DELETE /api/compositions/{id}`

---

## Endpoints da V5

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios` | Sim | Lista portfolios |
| POST | `/api/portfolios` | Sim | Cria portfolio |
| GET | `/api/portfolios/{id}` | Sim | Detalhes |
| PUT | `/api/portfolios/{id}` | Sim | Atualiza portfolio |
| DELETE | `/api/portfolios/{id}` | Sim | Remove portfolio |
| POST | `/api/portfolios/{id}/compositions` | Sim | Adiciona ativos |
| PUT | `/api/compositions/{id}` | Sim | Atualiza % |
| PUT | `/api/compositions/batch` | Sim | Atualiza multiplos |
| DELETE | `/api/compositions/{id}` | Sim | Remove ativo |

---

## Proxima Fase

Apos completar a V5, prosseguir para:
- **V6 - Crossing**: Comparacao entre portfolio ideal e posicoes reais
