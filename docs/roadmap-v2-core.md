# Roadmap V2 - Core (Banks + Accounts)

> Modulo base: bancos/corretoras e contas do usuario.

**Dependencia:** V1 (Autenticacao) completa

**Nota:** Migrations copiadas do `datagrana-web` (banco compartilhado).

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Seeders](#6-seeders)
7. [Form Requests](#7-form-requests)
8. [Resources](#8-resources)
9. [Policy](#9-policy)
10. [Controller](#10-controller)
11. [Rotas](#11-rotas)
12. [Casos de Teste](#12-casos-de-teste)
13. [Checklist de Implementacao](#13-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a estrutura de contas em corretoras:

- Listar bancos/corretoras disponiveis
- CRUD de contas do usuario
- Apenas uma conta pode ser `default` por usuario
- Conta so pode ser excluida se nao tiver posicoes ativas

**Entregaveis:**
- Tabelas `banks` e `accounts`
- Seeder com corretoras populares
- CRUD completo de accounts
- Testes automatizados

---

## 2. Dependencias

**Requer:** V1 (Autenticacao) completa

**Tabelas necessarias:**
- `users` (da V1)
- `personal_access_tokens` (da V1)

---

## 3. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AccountController.php
│   ├── Requests/
│   │   └── Account/
│   │       ├── StoreAccountRequest.php
│   │       └── UpdateAccountRequest.php
│   └── Resources/
│       ├── BankResource.php
│       └── AccountResource.php
├── Models/
│   ├── Bank.php
│   └── Account.php
└── Policies/
    └── AccountPolicy.php

database/
├── migrations/
│   ├── 2025_01_01_000001_create_banks_table.php
│   └── 2025_01_01_000002_create_accounts_table.php
└── seeders/
    └── BankSeeder.php

tests/
└── Feature/
    ├── Bank/
    │   └── BankListTest.php
    └── Account/
        ├── AccountIndexTest.php
        ├── AccountStoreTest.php
        ├── AccountShowTest.php
        ├── AccountUpdateTest.php
        └── AccountDestroyTest.php
```

---

## 4. Migrations

### 4.1 Migration: banks

**Arquivo:** `database/migrations/2025_01_01_000001_create_banks_table.php`

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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('nickname', 100)->nullable();
            $table->string('cnpj', 24)->nullable();
            $table->string('photo', 500)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
```

### 4.2 Migration: accounts

**Arquivo:** `database/migrations/2025_01_01_000002_create_accounts_table.php`

**Importante:** Copiar do `datagrana-web`. Ja executada no banco compartilhado.

**Unicidade:** `unique(['user_id', 'account'])` - permite mesmo nome de conta para usuarios diferentes.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account', 200);
            $table->string('nickname', 50)->nullable();
            $table->boolean('default')->default(false);
            $table->timestamps();

            // Unicidade por usuario (Opcao A)
            $table->unique(['user_id', 'account']);
            $table->index('default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

---

## 5. Models

### 5.1 Bank Model

**Arquivo:** `app/Models/Bank.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nickname',
        'cnpj',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * Contas vinculadas a este banco
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Scope para bancos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
```

### 5.2 Account Model

**Arquivo:** `app/Models/Account.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'account',
        'nickname',
        'default',
    ];

    protected function casts(): array
    {
        return [
            'default' => 'boolean',
        ];
    }

    /**
     * Usuario dono da conta
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Banco/corretora da conta
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Posicoes consolidadas desta conta
     * (sera implementado na V4)
     */
    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }

    /**
     * Verifica se a conta tem posicoes ativas
     */
    public function hasActivePositions(): bool
    {
        // Retorna false por enquanto (V4 implementa Consolidated)
        if (!class_exists(Consolidated::class)) {
            return false;
        }

        return $this->consolidated()->where('closed', false)->exists();
    }

    /**
     * Scope para conta padrao
     */
    public function scopeDefault($query)
    {
        return $query->where('default', true);
    }
}
```

### 5.3 Atualizar User Model

**Arquivo:** `app/Models/User.php` (adicionar relacionamento)

```php
// Adicionar ao User.php

/**
 * Contas do usuario em corretoras
 */
public function accounts(): HasMany
{
    return $this->hasMany(Account::class);
}

/**
 * Conta padrao do usuario
 */
public function defaultAccount()
{
    return $this->accounts()->where('default', true)->first();
}
```

---

## 6. Seeders

### 6.1 BankSeeder

**Arquivo:** `database/seeders/BankSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            [
                'name' => 'XP Investimentos',
                'nickname' => 'XP',
                'cnpj' => '02.332.886/0001-04',
                'status' => true,
            ],
            [
                'name' => 'Clear Corretora',
                'nickname' => 'Clear',
                'cnpj' => '02.332.886/0011-78',
                'status' => true,
            ],
            [
                'name' => 'Rico Investimentos',
                'nickname' => 'Rico',
                'cnpj' => '02.332.886/0012-59',
                'status' => true,
            ],
            [
                'name' => 'BTG Pactual',
                'nickname' => 'BTG',
                'cnpj' => '30.306.294/0001-45',
                'status' => true,
            ],
            [
                'name' => 'Nu Invest',
                'nickname' => 'Nubank',
                'cnpj' => '62.169.875/0001-79',
                'status' => true,
            ],
            [
                'name' => 'Inter Invest',
                'nickname' => 'Inter',
                'cnpj' => '18.945.670/0001-46',
                'status' => true,
            ],
            [
                'name' => 'Itau Corretora',
                'nickname' => 'Itau',
                'cnpj' => '61.194.353/0001-64',
                'status' => true,
            ],
            [
                'name' => 'Bradesco Corretora',
                'nickname' => 'Bradesco',
                'cnpj' => '61.855.045/0001-32',
                'status' => true,
            ],
            [
                'name' => 'Genial Investimentos',
                'nickname' => 'Genial',
                'cnpj' => '27.652.684/0001-62',
                'status' => true,
            ],
            [
                'name' => 'Avenue Securities',
                'nickname' => 'Avenue',
                'cnpj' => null,
                'status' => true,
            ],
            [
                'name' => 'Outra Corretora',
                'nickname' => 'Outra',
                'cnpj' => null,
                'status' => true,
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['name' => $bank['name']],
                array_merge($bank, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
```

### 6.2 DatabaseSeeder

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
        ]);
    }
}
```

---

## 7. Form Requests

### 7.1 StoreAccountRequest

**Arquivo:** `app/Http/Requests/Account/StoreAccountRequest.php`

```php
<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')->where('status', true),
            ],
            'account' => [
                'required',
                'string',
                'max:200',
                Rule::unique('accounts')
                    ->where('user_id', $this->user()->id),
            ],
            'nickname' => [
                'nullable',
                'string',
                'max:50',
            ],
            'default' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.exists' => 'Banco/corretora selecionado nao existe ou esta inativo.',
            'account.required' => 'O numero da conta e obrigatorio.',
            'account.unique' => 'Voce ja possui uma conta com este numero.',
            'account.max' => 'O numero da conta deve ter no maximo 200 caracteres.',
            'nickname.max' => 'O apelido deve ter no maximo 50 caracteres.',
        ];
    }
}
```

### 7.2 UpdateAccountRequest

**Arquivo:** `app/Http/Requests/Account/UpdateAccountRequest.php`

```php
<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')->where('status', true),
            ],
            'account' => [
                'sometimes',
                'required',
                'string',
                'max:200',
                Rule::unique('accounts')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('account')),
            ],
            'nickname' => [
                'nullable',
                'string',
                'max:50',
            ],
            'default' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.exists' => 'Banco/corretora selecionado nao existe ou esta inativo.',
            'account.required' => 'O numero da conta e obrigatorio.',
            'account.unique' => 'Voce ja possui uma conta com este numero.',
            'account.max' => 'O numero da conta deve ter no maximo 200 caracteres.',
            'nickname.max' => 'O apelido deve ter no maximo 50 caracteres.',
        ];
    }
}
```

---

## 8. Resources

### 8.1 BankResource

**Arquivo:** `app/Http/Resources/BankResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'cnpj' => $this->cnpj,
            'photo' => $this->photo,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### 8.2 AccountResource

**Arquivo:** `app/Http/Resources/AccountResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bank_id' => $this->bank_id,
            'account' => $this->account,
            'nickname' => $this->nickname,
            'default' => $this->default,
            'bank' => new BankResource($this->whenLoaded('bank')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## 9. Policy

### 9.1 AccountPolicy

**Arquivo:** `app/Policies/AccountPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * Usuario pode ver a conta?
     */
    public function view(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    /**
     * Usuario pode atualizar a conta?
     */
    public function update(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    /**
     * Usuario pode deletar a conta?
     */
    public function delete(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }
}
```

### 9.2 Registrar Policy

**Arquivo:** `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Models\Account;
use App\Policies\AccountPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
    }
}
```

---

## 10. Controller

### 10.1 AccountController

**Arquivo:** `app/Http/Controllers/Api/AccountController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BankResource;
use App\Models\Account;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends BaseController
{
    /**
     * GET /api/banks
     *
     * Lista bancos/corretoras disponiveis
     */
    public function banks(): JsonResponse
    {
        $banks = Bank::active()
            ->orderBy('name')
            ->get();

        return $this->sendResponse(BankResource::collection($banks));
    }

    /**
     * GET /api/accounts
     *
     * Lista contas do usuario
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->accounts()
            ->with('bank')
            ->orderBy('default', 'desc')
            ->orderBy('nickname')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(AccountResource::collection($accounts));
    }

    /**
     * POST /api/accounts
     *
     * Cria nova conta
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        // Se marcou como default, remove default das outras
        if ($request->boolean('default')) {
            $request->user()->accounts()->update(['default' => false]);
        }

        // Se e a primeira conta, marca como default automaticamente
        $isFirstAccount = $request->user()->accounts()->count() === 0;

        $account = $request->user()->accounts()->create([
            ...$request->validated(),
            'default' => $request->boolean('default') || $isFirstAccount,
        ]);

        return $this->sendResponse(
            new AccountResource($account->load('bank')),
            'Conta criada com sucesso.'
        );
    }

    /**
     * GET /api/accounts/{account}
     *
     * Exibe detalhes de uma conta
     */
    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return $this->sendResponse(
            new AccountResource($account->load('bank'))
        );
    }

    /**
     * PUT /api/accounts/{account}
     *
     * Atualiza uma conta
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        // Se marcou como default, remove default das outras
        if ($request->boolean('default')) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['default' => false]);
        }

        $account->update($request->validated());

        return $this->sendResponse(
            new AccountResource($account->fresh()->load('bank')),
            'Conta atualizada com sucesso.'
        );
    }

    /**
     * DELETE /api/accounts/{account}
     *
     * Remove uma conta
     */
    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        // Verifica se tem posicoes ativas
        if ($account->hasActivePositions()) {
            return $this->sendError(
                'Nao e possivel excluir uma conta com posicoes ativas. Encerre ou transfira as posicoes primeiro.',
                [],
                409
            );
        }

        $wasDefault = $account->default;
        $userId = $account->user_id;

        $account->delete();

        // Se era a conta default, define outra como default
        if ($wasDefault) {
            Account::where('user_id', $userId)
                ->orderBy('created_at')
                ->first()
                ?->update(['default' => true]);
        }

        return $this->sendResponse([], 'Conta removida com sucesso.');
    }
}
```

---

## 11. Rotas

### 11.1 Atualizar routes/api.php

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
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
| Rotas de Core (V2)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Banks (lista de corretoras - apenas para usuarios autenticados)
    Route::get('/banks', [AccountController::class, 'banks'])->name('banks.index');

    // Accounts (contas do usuario)
    Route::apiResource('accounts', AccountController::class);
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

## 12. Casos de Teste

### 12.1 Factories

**Arquivo:** `database/factories/BankFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BankFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Corretora',
            'nickname' => fake()->word(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'photo' => fake()->imageUrl(100, 100),
            'status' => true,
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

**Arquivo:** `database/factories/AccountFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::factory(),
            'account' => fake()->unique()->numerify('######-#'),
            'nickname' => fake()->word(),
            'default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'default' => true,
        ]);
    }

    public function withoutBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_id' => null,
        ]);
    }
}
```

### 12.2 BankListTest

**Arquivo:** `tests/Feature/Bank/BankListTest.php`

```php
<?php

namespace Tests\Feature\Bank;

use App\Models\Bank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_list_active_banks(): void
    {
        Bank::factory()->count(3)->create(['status' => true]);
        Bank::factory()->count(2)->create(['status' => false]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/banks', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'nickname',
                        'cnpj',
                        'photo',
                        'status',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function test_cannot_list_banks_without_authentication(): void
    {
        $response = $this->getJson('/api/banks');

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_banks_are_ordered_by_name(): void
    {
        Bank::factory()->create(['name' => 'Zebra Corretora']);
        Bank::factory()->create(['name' => 'Alpha Corretora']);
        Bank::factory()->create(['name' => 'Beta Corretora']);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/banks', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha Corretora', 'Beta Corretora', 'Zebra Corretora'], $names);
    }
}
```

### 12.3 AccountIndexTest

**Arquivo:** `tests/Feature/Account/AccountIndexTest.php`

```php
<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_list_own_accounts(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->count(3)->create(['user_id' => $auth['user']->id]);
        Account::factory()->count(2)->create(); // Outras contas

        $response = $this->getJson('/api/accounts', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'bank_id',
                        'account',
                        'nickname',
                        'default',
                        'bank',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function test_cannot_list_accounts_without_authentication(): void
    {
        $response = $this->getJson('/api/accounts');

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_default_account_comes_first(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'nickname' => 'Normal',
            'default' => false,
        ]);
        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'nickname' => 'Default',
            'default' => true,
        ]);

        $response = $this->getJson('/api/accounts', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $firstAccount = $response->json('data.0');
        $this->assertEquals('Default', $firstAccount['nickname']);
        $this->assertTrue($firstAccount['default']);
    }
}
```

### 12.4 AccountStoreTest

**Arquivo:** `tests/Feature/Account/AccountStoreTest.php`

```php
<?php

namespace Tests\Feature\Account;

use App\Models\Bank;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_create_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $bank = Bank::factory()->create();

        $response = $this->postJson('/api/accounts', [
            'bank_id' => $bank->id,
            'account' => '123456-7',
            'nickname' => 'Minha conta',
            'default' => false,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta criada com sucesso.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'account',
                    'nickname',
                    'default',
                    'bank',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $auth['user']->id,
            'bank_id' => $bank->id,
            'account' => '123456-7',
        ]);
    }

    /**
     * @test
     */
    public function test_first_account_is_automatically_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
            'default' => false,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
    }

    /**
     * @test
     */
    public function test_setting_new_default_removes_old_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $oldDefault = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
        ]);

        $response = $this->postJson('/api/accounts', [
            'account' => '999999-9',
            'default' => true,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
        $this->assertFalse($oldDefault->fresh()->default);
    }

    /**
     * @test
     */
    public function test_cannot_create_duplicate_account_number(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '123456-7',
        ]);

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }

    /**
     * @test
     */
    public function test_can_create_account_without_bank(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
            'nickname' => 'Conta sem banco',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertNull($response->json('data.bank_id'));
    }

    /**
     * @test
     */
    public function test_cannot_create_account_with_inactive_bank(): void
    {
        $auth = $this->createAuthenticatedUser();
        $bank = Bank::factory()->inactive()->create();

        $response = $this->postJson('/api/accounts', [
            'bank_id' => $bank->id,
            'account' => '123456-7',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_id']);
    }

    /**
     * @test
     */
    public function test_account_number_is_required(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'nickname' => 'Conta',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }
}
```

### 12.5 AccountShowTest

**Arquivo:** `tests/Feature/Account/AccountShowTest.php`

```php
<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_view_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->getJson("/api/accounts/{$account->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $account->id,
                    'account' => $account->account,
                ],
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_view_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->getJson("/api/accounts/{$otherAccount->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function test_returns_404_for_nonexistent_account(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/accounts/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }
}
```

### 12.6 AccountUpdateTest

**Arquivo:** `tests/Feature/Account/AccountUpdateTest.php`

```php
<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Bank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_update_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $newBank = Bank::factory()->create();

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'bank_id' => $newBank->id,
            'nickname' => 'Novo apelido',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta atualizada com sucesso.',
                'data' => [
                    'nickname' => 'Novo apelido',
                    'bank_id' => $newBank->id,
                ],
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_update_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->putJson("/api/accounts/{$otherAccount->id}", [
            'nickname' => 'Hacked',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function test_setting_default_removes_other_defaults(): void
    {
        $auth = $this->createAuthenticatedUser();

        $oldDefault = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
        ]);
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => false,
        ]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'default' => true,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
        $this->assertFalse($oldDefault->fresh()->default);
    }

    /**
     * @test
     */
    public function test_cannot_duplicate_account_number_on_update(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '111111-1',
        ]);
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '222222-2',
        ]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'account' => '111111-1',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }
}
```

### 12.7 AccountDestroyTest

**Arquivo:** `tests/Feature/Account/AccountDestroyTest.php`

```php
<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDestroyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_delete_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->deleteJson("/api/accounts/{$account->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta removida com sucesso.',
            ]);

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    /**
     * @test
     */
    public function test_cannot_delete_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->deleteJson("/api/accounts/{$otherAccount->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
        $this->assertDatabaseHas('accounts', ['id' => $otherAccount->id]);
    }

    /**
     * @test
     */
    public function test_deleting_default_assigns_new_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $defaultAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
            'created_at' => now()->subDay(),
        ]);
        $otherAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => false,
            'created_at' => now(),
        ]);

        $response = $this->deleteJson("/api/accounts/{$defaultAccount->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($otherAccount->fresh()->default);
    }

    /**
     * @test
     */
    public function test_can_delete_last_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertDatabaseCount('accounts', 0);
    }
}
```

---

## 13. Checklist de Implementacao

### 13.1 Database

- [ ] Criar migration `banks`
- [ ] Criar migration `accounts`
- [ ] Rodar `php artisan migrate`
- [ ] Criar `BankFactory`
- [ ] Criar `AccountFactory`
- [ ] Criar `BankSeeder`
- [ ] Rodar `php artisan db:seed`

### 13.2 Models

- [ ] Criar `Bank` model
- [ ] Criar `Account` model
- [ ] Atualizar `User` model (adicionar relacionamento)

### 13.3 Backend

- [ ] Criar `BankResource`
- [ ] Criar `AccountResource`
- [ ] Criar `StoreAccountRequest`
- [ ] Criar `UpdateAccountRequest`
- [ ] Criar `AccountPolicy`
- [ ] Registrar Policy no `AppServiceProvider`
- [ ] Criar `AccountController`
- [ ] Configurar rotas

### 13.4 Testes

- [ ] Criar `BankListTest`
- [ ] Criar `AccountIndexTest`
- [ ] Criar `AccountStoreTest`
- [ ] Criar `AccountShowTest`
- [ ] Criar `AccountUpdateTest`
- [ ] Criar `AccountDestroyTest`
- [ ] Rodar `php artisan test` - todos passando

### 13.5 Validacao Final

- [ ] Testar `GET /api/banks`
- [ ] Testar `GET /api/accounts`
- [ ] Testar `POST /api/accounts`
- [ ] Testar `GET /api/accounts/{id}`
- [ ] Testar `PUT /api/accounts/{id}`
- [ ] Testar `DELETE /api/accounts/{id}`

---

## Endpoints da V2

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/banks` | Sim | Lista corretoras |
| GET | `/api/accounts` | Sim | Lista contas do usuario |
| POST | `/api/accounts` | Sim | Cria nova conta |
| GET | `/api/accounts/{id}` | Sim | Detalhes da conta |
| PUT | `/api/accounts/{id}` | Sim | Atualiza conta |
| DELETE | `/api/accounts/{id}` | Sim | Remove conta |

---

## Proxima Fase

Apos completar a V2, prosseguir para:
- **V3 - Companies**: Categorias, empresas e tickers (ativos de renda variavel)
