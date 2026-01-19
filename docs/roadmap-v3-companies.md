# Roadmap V3 - Companies (Ativos de Renda Variavel)

> Estrutura de categorias, empresas e tickers para ativos de renda variavel.

> **Nota Importante:** As migrations desta fase foram copiadas do projeto `datagrana-web`, pois ambos os projetos compartilham o mesmo banco de dados. As migrations já foram executadas no banco compartilhado.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Seeders](#6-seeders)
7. [Resources](#7-resources)
8. [Controller](#8-controller)
9. [Rotas](#9-rotas)
10. [Casos de Teste](#10-casos-de-teste)
11. [Checklist de Implementacao](#11-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a estrutura de ativos de renda variavel:

- Categorias: Acoes, FIIs, ETFs
- Empresas: Petrobras, Vale, HGLG11, etc.
- Tickers: PETR4, VALE3, HGLG11

**Entregaveis:**
- Tabelas `company_category`, `companies`, `company_tickers`
- Seeders com categorias
- Endpoint de busca de ativos
- Testes automatizados

---

## 2. Dependencias

**Requer:** V2 (Core) completa

**Tabelas necessarias:**
- `users`
- `banks`
- `accounts`

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AssetController.php
│   └── Resources/
│       ├── CompanyCategoryResource.php
│       ├── CompanyResource.php
│       └── CompanyTickerResource.php
└── Models/
    ├── CompanyCategory.php
    ├── Company.php
    └── CompanyTicker.php

database/
├── migrations/
│   ├── 2025_01_01_000003_create_company_category_table.php
│   ├── 2025_01_01_000004_create_companies_table.php
│   └── 2025_01_01_000005_create_company_tickers_table.php
└── seeders/
    └── CompanyCategorySeeder.php

tests/
└── Feature/
    └── Asset/
        ├── AssetCategoriesTest.php
        ├── AssetSearchTest.php
        └── AssetShowTest.php
```

---

## 4. Migrations

### 4.1 Migration: company_category

**Importante:** Copiar do `datagrana-web`. Ja executada no banco compartilhado.

**Arquivo:** `database/migrations/2025_01_01_000003_create_company_category_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_category', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('short_name', 100);
            $table->string('reference', 30); // "Acoes", "FII", "ETF"
            $table->boolean('status')->default(true);
            $table->string('color_hex', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_category');
    }
};
```

### 4.2 Migration: companies

**Importante:** Copiar do `datagrana-web`. Ja executada no banco compartilhado.

**Arquivo:** `database/migrations/2025_01_01_000004_create_companies_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_category_id')->constrained('company_category');
            $table->string('name', 200);
            $table->boolean('status')->default(true);
            $table->char('cnpj', 18)->nullable();
            $table->string('nickname', 200)->nullable();
            $table->text('photo')->nullable();
            $table->string('segment', 80)->nullable();
            $table->string('sector', 80)->nullable();
            $table->string('subsector', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

### 4.3 Migration: company_tickers

**Importante:** Copiar do `datagrana-web`. Ja executada no banco compartilhado.

**Arquivo:** `database/migrations/2025_01_01_000005_create_company_tickers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_tickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12); // Ex: "PETR4", "HGLG11"
            $table->string('trade_code', 12)->default('BVMF');
            $table->boolean('status')->default(true);
            $table->boolean('can_update')->default(true);
            $table->decimal('last_price', 18, 8)->nullable();
            $table->timestamp('last_price_updated')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_tickers');
    }
};
```

---

## 5. Models

### 5.1 CompanyCategory Model

**Arquivo:** `app/Models/CompanyCategory.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCategory extends Model
{
    use HasFactory;

    protected $table = 'company_category';

    protected $fillable = [
        'name',
        'short_name',
        'reference',
        'status',
        'color_hex',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * Empresas desta categoria
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Scope para categorias ativas
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
```

### 5.2 Company Model

**Arquivo:** `app/Models/Company.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_category_id',
        'name',
        'status',
        'cnpj',
        'nickname',
        'photo',
        'segment',
        'sector',
        'subsector',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * Categoria da empresa
     */
    public function companyCategory(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class);
    }

    /**
     * Alias para categoria
     */
    public function category(): BelongsTo
    {
        return $this->companyCategory();
    }

    /**
     * Tickers da empresa
     */
    public function tickers(): HasMany
    {
        return $this->hasMany(CompanyTicker::class);
    }

    /**
     * Scope para empresas ativas
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
```

### 5.3 CompanyTicker Model

**Arquivo:** `app/Models/CompanyTicker.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyTicker extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'trade_code',
        'status',
        'can_update',
        'last_price',
        'last_price_updated',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'can_update' => 'boolean',
            'last_price' => 'decimal:8',
            'last_price_updated' => 'datetime',
        ];
    }

    /**
     * Empresa dona do ticker
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Posicoes consolidadas deste ticker
     * (sera implementado na V4)
     */
    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }

    /**
     * Composicoes de portfolio com este ticker
     * (sera implementado na V5)
     */
    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }

    /**
     * Scope para tickers ativos
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Retorna nome formatado: TICKER - Empresa
     */
    public function getFormattedNameAttribute(): string
    {
        return "{$this->code} - {$this->company->name}";
    }
}
```

---

## 6. Seeders

### 6.1 CompanyCategorySeeder

**Arquivo:** `database/seeders/CompanyCategorySeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\CompanyCategory;
use Illuminate\Database\Seeder;

class CompanyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Acoes',
                'short_name' => 'Acoes',
                'reference' => 'Acoes',
                'status' => true,
                'color_hex' => '#3B82F6',
                'icon' => 'chart-line',
            ],
            [
                'name' => 'Fundos Imobiliarios',
                'short_name' => 'FIIs',
                'reference' => 'FII',
                'status' => true,
                'color_hex' => '#10B981',
                'icon' => 'building',
            ],
            [
                'name' => 'ETFs',
                'short_name' => 'ETFs',
                'reference' => 'ETF',
                'status' => true,
                'color_hex' => '#8B5CF6',
                'icon' => 'layer-group',
            ],
            [
                'name' => 'BDRs',
                'short_name' => 'BDRs',
                'reference' => 'BDR',
                'status' => true,
                'color_hex' => '#F59E0B',
                'icon' => 'globe',
            ],
        ];

        foreach ($categories as $category) {
            CompanyCategory::updateOrCreate(
                ['reference' => $category['reference']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
```

### 6.2 Atualizar DatabaseSeeder

**Arquivo:** `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BankSeeder::class,
            CompanyCategorySeeder::class,
        ]);
    }
}
```

---

## 7. Resources

### 7.1 CompanyCategoryResource

**Arquivo:** `app/Http/Resources/CompanyCategoryResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'reference' => $this->reference,
            'color_hex' => $this->color_hex,
            'icon' => $this->icon,
            'status' => $this->status,
        ];
    }
}
```

### 7.2 CompanyResource

**Arquivo:** `app/Http/Resources/CompanyResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'cnpj' => $this->cnpj,
            'photo' => $this->photo,
            'segment' => $this->segment,
            'sector' => $this->sector,
            'subsector' => $this->subsector,
            'status' => $this->status,
            'category' => new CompanyCategoryResource($this->whenLoaded('companyCategory')),
            'tickers' => CompanyTickerResource::collection($this->whenLoaded('tickers')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### 7.3 CompanyTickerResource

**Arquivo:** `app/Http/Resources/CompanyTickerResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTickerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'trade_code' => $this->trade_code,
            'status' => $this->status,
            'can_update' => $this->can_update,
            'last_price' => $this->last_price ? (string) $this->last_price : null,
            'last_price_updated' => $this->last_price_updated?->toISOString(),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## 8. Controller

### 8.1 AssetController

**Arquivo:** `app/Http/Controllers/Api/AssetController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CompanyCategoryResource;
use App\Http\Resources\CompanyTickerResource;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends BaseController
{
    /**
     * GET /api/companies/categories
     *
     * Lista categorias de ativos (Acoes, FIIs, ETFs)
     */
    public function categories(): JsonResponse
    {
        $categories = CompanyCategory::active()
            ->orderBy('name')
            ->get();

        return $this->sendResponse(CompanyCategoryResource::collection($categories));
    }

    /**
     * GET /api/companies?search=
     *
     * Busca ativos por codigo ou nome
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:company_category,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = $request->search;
        $limit = $request->integer('limit', 20);

        $tickers = CompanyTicker::active()
            ->whereHas('company', fn($q) => $q->active())
            ->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('nickname', 'like', "%{$search}%");
                    });
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->whereHas('company', fn($q) =>
                    $q->where('company_category_id', $categoryId)
                );
            })
            ->with(['company.companyCategory'])
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END", ["{$search}%"])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return $this->sendResponse(CompanyTickerResource::collection($tickers));
    }

    /**
     * GET /api/companies/{companyTicker}
     *
     * Exibe detalhes de um ativo
     */
    public function show(CompanyTicker $companyTicker): JsonResponse
    {
        return $this->sendResponse(
            new CompanyTickerResource(
                $companyTicker->load('company.companyCategory')
            )
        );
    }

    /**
     * GET /api/companies/popular
     *
     * Lista ativos populares (para sugestoes)
     */
    public function popular(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:company_category,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = $request->integer('limit', 10);

        // Por enquanto retorna os primeiros ativos
        // Futuramente pode ser baseado em uso real
        $tickers = CompanyTicker::active()
            ->whereHas('company', fn($q) => $q->active())
            ->when($request->category_id, function ($query, $categoryId) {
                $query->whereHas('company', fn($q) =>
                    $q->where('company_category_id', $categoryId)
                );
            })
            ->with(['company.companyCategory'])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return $this->sendResponse(CompanyTickerResource::collection($tickers));
    }
}
```

---

## 9. Rotas

### 9.1 Atualizar routes/api.php

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Autenticacao (V1)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'google'])->name('auth.google');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    });
});

/*
|--------------------------------------------------------------------------
| Rotas Autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Banks (V2)
    Route::get('/banks', [AccountController::class, 'banks'])->name('banks.index');

    // Accounts (V2)
    Route::apiResource('accounts', AccountController::class);

    // Companies (V3)
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/categories', [AssetController::class, 'categories'])->name('categories');
        Route::get('/', [AssetController::class, 'search'])->name('search');
        Route::get('/popular', [AssetController::class, 'popular'])->name('popular');
        Route::get('/{companyTicker}', [AssetController::class, 'show'])->name('show');
    });
});

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
    ]);
})->name('health');
```

---

## 10. Casos de Teste

### 10.1 Factories

**Arquivo:** `database/factories/CompanyCategoryFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyCategoryFactory extends Factory
{
    public function definition(): array
    {
        $references = ['Acoes', 'FII', 'ETF', 'BDR'];

        return [
            'name' => fake()->unique()->company() . ' Category',
            'short_name' => fake()->word(),
            'reference' => fake()->randomElement($references),
            'status' => true,
            'color_hex' => fake()->hexColor(),
            'icon' => fake()->word(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function acoes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Acoes',
            'short_name' => 'Acoes',
            'reference' => 'Acoes',
        ]);
    }

    public function fii(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Fundos Imobiliarios',
            'short_name' => 'FIIs',
            'reference' => 'FII',
        ]);
    }
}
```

**Arquivo:** `database/factories/CompanyFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CompanyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_category_id' => CompanyCategory::factory(),
            'name' => fake()->unique()->company(),
            'status' => true,
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'nickname' => fake()->word(),
            'photo' => fake()->imageUrl(100, 100),
            'segment' => fake()->word(),
            'sector' => fake()->word(),
            'subsector' => fake()->word(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
```

**Arquivo:** `database/factories/CompanyTickerFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyTickerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->lexify('????')) . fake()->randomNumber(1),
            'trade_code' => 'BVMF',
            'status' => true,
            'can_update' => true,
            'last_price' => fake()->randomFloat(2, 10, 100),
            'last_price_updated' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function withoutPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_price' => null,
            'last_price_updated' => null,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}
```

### 10.2 AssetCategoriesTest

**Arquivo:** `tests/Feature/Asset/AssetCategoriesTest.php`

```php
<?php

namespace Tests\Feature\Asset;

use App\Models\CompanyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCategoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_list_active_categories(): void
    {
        CompanyCategory::factory()->count(3)->create(['status' => true]);
        CompanyCategory::factory()->count(2)->create(['status' => false]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/categories', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'short_name',
                        'reference',
                        'color_hex',
                        'icon',
                        'status',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function test_cannot_list_categories_without_authentication(): void
    {
        $response = $this->getJson('/api/companies/categories');

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_categories_are_ordered_by_name(): void
    {
        CompanyCategory::factory()->create(['name' => 'Zebra']);
        CompanyCategory::factory()->create(['name' => 'Alpha']);
        CompanyCategory::factory()->create(['name' => 'Beta']);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/categories', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha', 'Beta', 'Zebra'], $names);
    }
}
```

### 10.3 AssetSearchTest

**Arquivo:** `tests/Feature/Asset/AssetSearchTest.php`

```php
<?php

namespace Tests\Feature\Asset;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_search_assets_by_ticker_code(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'VALE3',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=PETR', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PETR4');
    }

    /**
     * @test
     */
    public function test_can_search_assets_by_company_name(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create([
            'company_category_id' => $category->id,
            'name' => 'Petrobras S.A.',
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=Petrobras', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * @test
     */
    public function test_can_filter_search_by_category(): void
    {
        $acoes = CompanyCategory::factory()->acoes()->create();
        $fiis = CompanyCategory::factory()->fii()->create();

        $companyAcao = Company::factory()->create(['company_category_id' => $acoes->id]);
        $companyFii = Company::factory()->create(['company_category_id' => $fiis->id]);

        CompanyTicker::factory()->create([
            'company_id' => $companyAcao->id,
            'code' => 'TEST3',
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $companyFii->id,
            'code' => 'TEST11',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson(
            "/api/companies?search=TEST&category_id={$acoes->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TEST3');
    }

    /**
     * @test
     */
    public function test_search_excludes_inactive_tickers(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'ACTIVE4',
            'status' => true,
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'INACTIVE3',
            'status' => false,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=ACTIVE', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/companies?search=INACTIVE', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /**
     * @test
     */
    public function test_search_requires_minimum_characters(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=P', $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    /**
     * @test
     */
    public function test_search_respects_limit_parameter(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        CompanyTicker::factory()->count(10)->create([
            'company_id' => $company->id,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=test&limit=3', $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(3, count($response->json('data')));
    }

    /**
     * @test
     */
    public function test_cannot_search_without_authentication(): void
    {
        $response = $this->getJson('/api/companies?search=PETR');

        $response->assertStatus(401);
    }
}
```

### 10.4 AssetShowTest

**Arquivo:** `tests/Feature/Asset/AssetShowTest.php`

```php
<?php

namespace Tests\Feature\Asset;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_view_asset_details(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create([
            'company_category_id' => $category->id,
            'name' => 'Petrobras',
        ]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
            'last_price' => 35.50,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson("/api/companies/{$ticker->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'code',
                    'last_price',
                    'company' => [
                        'id',
                        'name',
                        'category',
                    ],
                ],
            ])
            ->assertJsonPath('data.code', 'PETR4')
            ->assertJsonPath('data.company.name', 'Petrobras');
    }

    /**
     * @test
     */
    public function test_returns_404_for_nonexistent_asset(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function test_cannot_view_asset_without_authentication(): void
    {
        $ticker = CompanyTicker::factory()->create();

        $response = $this->getJson("/api/companies/{$ticker->id}");

        $response->assertStatus(401);
    }
}
```

---

## 11. Checklist de Implementacao

### 11.1 Database

- [ ] Criar migration `company_category`
- [ ] Criar migration `companies`
- [ ] Criar migration `company_tickers`
- [ ] Rodar `php artisan migrate`
- [ ] Criar `CompanyCategoryFactory`
- [ ] Criar `CompanyFactory`
- [ ] Criar `CompanyTickerFactory`
- [ ] Criar `CompanyCategorySeeder`
- [ ] Atualizar `DatabaseSeeder`
- [ ] Rodar `php artisan db:seed`

### 11.2 Models

- [ ] Criar `CompanyCategory` model
- [ ] Criar `Company` model
- [ ] Criar `CompanyTicker` model

### 11.3 Backend

- [ ] Criar `CompanyCategoryResource`
- [ ] Criar `CompanyResource`
- [ ] Criar `CompanyTickerResource`
- [ ] Criar `AssetController`
- [ ] Configurar rotas

### 11.4 Testes

- [ ] Criar `AssetCategoriesTest`
- [ ] Criar `AssetSearchTest`
- [ ] Criar `AssetShowTest`
- [ ] Rodar `php artisan test` - todos passando

### 11.5 Validacao Final

- [ ] Testar `GET /api/companies/categories`
- [ ] Testar `GET /api/companies?search=XXX`
- [ ] Testar `GET /api/companies?search=XXX&category_id=X`
- [ ] Testar `GET /api/companies/popular`
- [ ] Testar `GET /api/companies/{id}`

---

## Endpoints da V3

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/companies/categories` | Sim | Lista categorias |
| GET | `/api/companies?search=` | Sim | Busca ativos |
| GET | `/api/companies/popular` | Sim | Ativos populares |
| GET | `/api/companies/{id}` | Sim | Detalhes do ativo |

---

## Proxima Fase

Apos completar a V3, prosseguir para:
- **V4 - Consolidated**: Posicoes reais do usuario (compras de ativos)
