# Roadmap - DataGrana Portfolio API

> Documentacao completa para implementacao da API do modulo de Carteiras de Investimento usando Laravel + Sanctum para consumo em React Native.

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Arquitetura do Sistema](#2-arquitetura-do-sistema)
3. [Estrutura de Banco de Dados](#3-estrutura-de-banco-de-dados)
4. [Sistema de Autenticacao](#4-sistema-de-autenticacao)
5. [Models e Relacionamentos](#5-models-e-relacionamentos)
6. [Controllers e Endpoints API](#6-controllers-e-endpoints-api)
7. [Services e Helpers](#7-services-e-helpers)
8. [Regras de Negocio](#8-regras-de-negocio)
9. [Fases de Implementacao](#9-fases-de-implementacao)
10. [Dependencias e Pacotes](#10-dependencias-e-pacotes)

---

## 1. Visao Geral

### 1.1 O que e o modulo Portfolio

O **Portfolio** e um sistema de gestao de carteiras de investimento em **Renda Variavel** (Acoes, FIIs, ETFs) que permite aos usuarios:

- Criar multiplas carteiras com metas financeiras
- Definir alocacao percentual ideal de ativos
- Comparar a composicao ideal com a posicao real consolidada
- Rastrear historico de modificacoes na carteira
- Calcular quanto comprar de cada ativo para atingir a meta

### 1.2 Fluxo do Usuario

```
1. Login com Google (cria usuario se nao existe)
2. Criar uma Account (conta na corretora)
3. Cadastrar compras de ativos (posicoes consolidadas)
4. Criar Portfolio com ativos e percentuais
5. Visualizar comparacao (Crossing)
```

### 1.3 Funcionalidades Principais

| Funcionalidade | Descricao |
|----------------|-----------|
| **Login Social** | Autenticacao exclusiva via Google OAuth |
| **Accounts** | Contas em corretoras para organizar investimentos |
| **Compras** | Registro de compras de ativos (posicao consolidada) |
| **Portfolio** | Nome, investimento mensal, objetivo/meta |
| **Composicao** | Percentual desejado para cada ativo |
| **Crossing** | Comparar ideal vs real e calcular quanto comprar |
| **Historico** | Rastrear ativos removidos com motivos |

### 1.4 Stack Tecnologico

**Backend:**
- PHP 8.2+
- Laravel 12
- Laravel Sanctum (autenticacao API)
- MySQL/MariaDB

**Frontend (consumidor):**
- React Native + Expo
- TypeScript

---

## 2. Arquitetura do Sistema

### 2.1 Estrutura de Diretorios

```
datagrana-portfolio/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── BaseController.php
│   │   │       ├── AuthController.php
│   │   │       ├── AccountController.php
│   │   │       ├── AssetController.php
│   │   │       ├── ConsolidatedController.php
│   │   │       ├── PortfolioController.php
│   │   │       └── CompositionController.php
│   │   ├── Requests/
│   │   │   ├── Auth/
│   │   │   │   └── GoogleAuthRequest.php
│   │   │   ├── Account/
│   │   │   │   ├── StoreAccountRequest.php
│   │   │   │   └── UpdateAccountRequest.php
│   │   │   ├── Consolidated/
│   │   │   │   ├── StoreConsolidatedRequest.php
│   │   │   │   └── UpdateConsolidatedRequest.php
│   │   │   └── Portfolio/
│   │   │       ├── StorePortfolioRequest.php
│   │   │       ├── UpdatePortfolioRequest.php
│   │   │       ├── StoreCompositionRequest.php
│   │   │       └── UpdateCompositionRequest.php
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       ├── AccountResource.php
│   │       ├── CompanyTickerResource.php
│   │       ├── ConsolidatedResource.php
│   │       ├── PortfolioResource.php
│   │       └── CompositionResource.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Bank.php
│   │   ├── Account.php
│   │   ├── CompanyCategory.php
│   │   ├── Company.php
│   │   ├── CompanyTicker.php
│   │   ├── Consolidated.php
│   │   ├── Portfolio.php
│   │   ├── Composition.php
│   │   └── CompositionHistory.php
│   ├── Services/
│   │   ├── Auth/
│   │   │   └── GoogleAuthService.php
│   │   └── Portfolio/
│   │       └── CrossingService.php
│   ├── Helpers/
│   │   └── PortfolioHelper.php
│   └── Policies/
│       ├── AccountPolicy.php
│       ├── ConsolidatedPolicy.php
│       └── PortfolioPolicy.php
├── config/
│   ├── auth.php
│   ├── sanctum.php
│   └── services.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
└── docs/
    ├── roadmap-app.md
    └── patterns/
```

### 2.2 Diagrama de Dependencias

```
┌─────────────────────────────────────────────────────────────┐
│                     PORTFOLIO (Nucleo)                      │
├─────────────────────────────────────────────────────────────┤
│  Portfolio → belongsTo(User)                               │
│  Portfolio → hasMany(Composition)                          │
│  Portfolio → hasMany(CompositionHistory)                   │
│                                                             │
│  Composition → belongsTo(CompanyTicker)                    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              CONSOLIDATED (Posicao Real)                   │
├─────────────────────────────────────────────────────────────┤
│  Consolidated → belongsTo(Account)                         │
│  Consolidated → belongsTo(CompanyTicker)                   │
│  Accessors: balance, profit, profit_percentage             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              COMPANIES (Renda Variavel)                    │
├─────────────────────────────────────────────────────────────┤
│  CompanyCategory → hasMany(Company)                        │
│  Company → hasMany(CompanyTicker)                          │
│  CompanyTicker → hasMany(Consolidated)                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              CORE (Base)                                   │
├─────────────────────────────────────────────────────────────┤
│  User → hasMany(Account) → hasMany(Portfolio)              │
│  Bank → hasMany(Account)                                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Estrutura de Banco de Dados

### 3.1 Visao Geral das Tabelas

| Tabela | Descricao |
|--------|-----------|
| `users` | Usuarios do sistema (login Google) |
| `personal_access_tokens` | Tokens Sanctum |
| `banks` | Bancos/Corretoras disponiveis |
| `accounts` | Contas do usuario em corretoras |
| `company_category` | Categorias de ativos (Acoes, FIIs, ETFs) |
| `companies` | Empresas listadas na bolsa |
| `company_tickers` | Codigos de negociacao (PETR4, HGLG11) |
| `consolidated` | Posicoes reais do usuario (compras) |
| `portfolios` | Carteiras de investimento |
| `portfolio_compositions` | Composicao ideal da carteira |
| `portfolio_composition_histories` | Historico de remocoes |

### 3.2 Migrations

#### users
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('photo', 500)->nullable();
    $table->boolean('status')->default(true);
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable(); // Nullable para OAuth
    $table->rememberToken();
    $table->text('google_id')->nullable();
    $table->timestamps();
});
```

#### personal_access_tokens (Sanctum)
```php
// Criada automaticamente pelo Sanctum
Schema::create('personal_access_tokens', function (Blueprint $table) {
    $table->id();
    $table->morphs('tokenable');
    $table->string('name');
    $table->string('token', 64)->unique();
    $table->text('abilities')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

#### banks
```php
Schema::create('banks', function (Blueprint $table) {
    $table->id();
    $table->string('name', 200);
    $table->string('nickname', 100)->nullable();
    $table->string('cnpj', 24)->nullable();
    $table->string('photo', 500)->nullable();
    $table->boolean('status')->default(true);
    $table->timestamps();
});
```

#### accounts
```php
Schema::create('accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
    $table->string('account', 200)->unique();
    $table->string('nickname', 50)->nullable();
    $table->boolean('default')->default(false);
    $table->timestamps();
});
```

#### company_category
```php
Schema::create('company_category', function (Blueprint $table) {
    $table->id();
    $table->string('name', 200);
    $table->string('short_name', 100);
    $table->string('reference', 30); // "Acoes", "FII", "ETF"
    $table->boolean('status')->default(true);
    $table->string('color_hex', 50)->nullable();
    $table->string('icon', 50)->nullable();
    $table->timestamps();
});
```

#### companies
```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_category_id')->constrained('company_category');
    $table->string('name', 200);
    $table->tinyInteger('status')->default(1);
    $table->char('cnpj', 18)->nullable();
    $table->string('nickname', 200)->nullable();
    $table->text('photo')->nullable();
    $table->string('segment', 80)->nullable();
    $table->string('sector', 80)->nullable();
    $table->string('subsector', 80)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

#### company_tickers
```php
Schema::create('company_tickers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('code', 12); // Ex: "PETR4", "HGLG11"
    $table->string('trade_code', 12)->default('BVMF');
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('can_update')->default(1);
    $table->decimal('last_price', 18, 8)->nullable();
    $table->timestamp('last_price_updated')->nullable();
    $table->timestamps();
});
```

#### consolidated
```php
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
});
```

#### portfolios
```php
Schema::create('portfolios', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 80);
    $table->decimal('month_value', 12, 2)->default(0);
    $table->decimal('target_value', 12, 2)->default(0);
    $table->timestamps();
    $table->softDeletes();
});
```

#### portfolio_compositions
```php
Schema::create('portfolio_compositions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
    $table->foreignId('company_ticker_id')->constrained()->cascadeOnDelete();
    $table->decimal('percentage', 10, 2);
    $table->timestamps();

    $table->unique(['portfolio_id', 'company_ticker_id']);
});
```

#### portfolio_composition_histories
```php
Schema::create('portfolio_composition_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
    $table->foreignId('company_ticker_id')->constrained()->cascadeOnDelete();
    $table->decimal('percentage', 10, 2)->nullable();
    $table->string('reason', 500)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 3.3 Seeders

#### CompanyCategorySeeder
```php
<?php

namespace Database\Seeders;

use App\Models\CompanyCategory;
use Illuminate\Database\Seeder;

class CompanyCategorySeeder extends Seeder
{
    public function run(): void
    {
        CompanyCategory::insert([
            [
                'name' => 'Acoes',
                'short_name' => 'Acoes',
                'reference' => 'Acoes',
                'status' => true,
                'color_hex' => '#3B82F6',
                'icon' => 'chart-line',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fundos Imobiliarios',
                'short_name' => 'FIIs',
                'reference' => 'FII',
                'status' => true,
                'color_hex' => '#10B981',
                'icon' => 'building',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ETFs',
                'short_name' => 'ETFs',
                'reference' => 'ETF',
                'status' => true,
                'color_hex' => '#8B5CF6',
                'icon' => 'layer-group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
```

#### BankSeeder
```php
<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        Bank::insert([
            ['name' => 'XP Investimentos', 'nickname' => 'XP', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Clear Corretora', 'nickname' => 'Clear', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rico Investimentos', 'nickname' => 'Rico', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'BTG Pactual', 'nickname' => 'BTG', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nu Invest', 'nickname' => 'Nubank', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inter Invest', 'nickname' => 'Inter', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
```

---

## 4. Sistema de Autenticacao

### 4.1 Visao Geral

O projeto usa **apenas Google OAuth** para autenticacao:

```
1. App React Native → Solicita login Google (Expo AuthSession)
2. Google retorna id_token
3. App envia id_token para API Laravel
4. API valida token com Google
5. API cria/atualiza usuario
6. API retorna Bearer token Sanctum
7. App armazena token (SecureStore)
8. Requisicoes subsequentes usam Authorization: Bearer {token}
```

### 4.2 Configuracao

#### config/services.php
```php
return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
];
```

#### .env
```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### 4.3 GoogleAuthService

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use Google_Client;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client([
            'client_id' => config('services.google.client_id'),
        ]);
    }

    /**
     * Valida o id_token do Google e retorna os dados do usuario
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if (!$payload) {
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'] ?? null,
                'photo' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Google token verification failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Encontra ou cria usuario baseado nos dados do Google
     */
    public function findOrCreateUser(array $googleData): User
    {
        $user = User::where('google_id', $googleData['google_id'])
            ->orWhere('email', $googleData['email'])
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleData['google_id'],
                'photo' => $googleData['photo'] ?? $user->photo,
                'name' => $googleData['name'] ?? $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'google_id' => $googleData['google_id'],
                'email' => $googleData['email'],
                'name' => $googleData['name'] ?? 'Usuario',
                'photo' => $googleData['photo'],
                'email_verified_at' => now(),
                'status' => true,
            ]);
        }

        return $user;
    }
}
```

### 4.4 AuthController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    /**
     * POST /api/auth/google
     */
    public function google(GoogleAuthRequest $request)
    {
        $googleData = $this->googleAuthService->verifyIdToken($request->id_token);

        if (!$googleData) {
            return $this->sendError('Token do Google invalido ou expirado.', [], 401);
        }

        $user = $this->googleAuthService->findOrCreateUser($googleData);

        if (!$user->status) {
            return $this->sendError('Sua conta esta desativada.', [], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login realizado com sucesso.');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return $this->sendResponse([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Logout realizado com sucesso.');
    }

    /**
     * POST /api/auth/logout-all
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->sendResponse([], 'Logout de todos os dispositivos realizado.');
    }
}
```

### 4.5 GoogleAuthRequest

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
        ];
    }
}
```

---

## 5. Models e Relacionamentos

### 5.1 User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'google_id',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }
}
```

### 5.2 Bank Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
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

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
```

### 5.3 Account Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }
}
```

### 5.4 CompanyCategory Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCategory extends Model
{
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

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
```

### 5.5 Company Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

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

    public function companyCategory(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class);
    }

    public function tickers(): HasMany
    {
        return $this->hasMany(CompanyTicker::class);
    }
}
```

### 5.6 CompanyTicker Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyTicker extends Model
{
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }

    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }
}
```

### 5.7 Consolidated Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Consolidated extends Model
{
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }

    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity_current * ($this->companyTicker?->last_price ?? 0)
        );
    }

    protected function profit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->balance - $this->total_purchased
        );
    }

    protected function profitPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_purchased > 0
                ? (($this->balance - $this->total_purchased) / $this->total_purchased) * 100
                : 0
        );
    }
}
```

### 5.8 Portfolio Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Portfolio extends Model
{
    use SoftDeletes;

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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }

    public function compositionHistories(): HasMany
    {
        return $this->hasMany(CompositionHistory::class);
    }

    protected function totalPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->compositions()->sum('percentage')
        );
    }
}
```

### 5.9 Composition Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Composition extends Model
{
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

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}
```

### 5.10 CompositionHistory Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompositionHistory extends Model
{
    use SoftDeletes;

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

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}
```

---

## 6. Controllers e Endpoints API

### 6.1 BaseController

Seguindo o padrao documentado em `docs/patterns/controllers.md`:

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    /**
     * Resposta de sucesso
     */
    protected function sendResponse($result, string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }

    /**
     * Resposta de erro
     */
    protected function sendError(string $error, array $errorMessages = [], int $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
```

### 6.2 AccountController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends BaseController
{
    /**
     * GET /api/accounts
     */
    public function index(Request $request)
    {
        $accounts = $request->user()->accounts()
            ->with('bank')
            ->orderBy('default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(AccountResource::collection($accounts));
    }

    /**
     * POST /api/accounts
     */
    public function store(StoreAccountRequest $request)
    {
        // Se marcou como default, remove default das outras
        if ($request->boolean('default')) {
            $request->user()->accounts()->update(['default' => false]);
        }

        $account = $request->user()->accounts()->create($request->validated());

        return $this->sendResponse(
            new AccountResource($account->load('bank')),
            'Conta criada com sucesso.'
        );
    }

    /**
     * GET /api/accounts/{account}
     */
    public function show(Account $account)
    {
        $this->authorize('view', $account);

        return $this->sendResponse(
            new AccountResource($account->load('bank', 'consolidated.companyTicker.company.companyCategory'))
        );
    }

    /**
     * PUT /api/accounts/{account}
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        $this->authorize('update', $account);

        if ($request->boolean('default')) {
            $request->user()->accounts()->update(['default' => false]);
        }

        $account->update($request->validated());

        return $this->sendResponse(
            new AccountResource($account->fresh()->load('bank')),
            'Conta atualizada com sucesso.'
        );
    }

    /**
     * DELETE /api/accounts/{account}
     */
    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        if ($account->consolidated()->exists()) {
            return $this->sendError(
                'Nao e possivel excluir uma conta com posicoes ativas.',
                [],
                409
            );
        }

        $account->delete();

        return $this->sendResponse([], 'Conta removida com sucesso.');
    }
}
```

### 6.3 AssetController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CompanyTickerResource;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Http\Request;

class AssetController extends BaseController
{
    /**
     * GET /api/assets/search
     */
    public function search(Request $request)
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2'],
            'category_id' => ['nullable', 'integer', 'exists:company_category,id'],
        ]);

        $search = $request->search;

        $tickers = CompanyTicker::where('status', true)
            ->where(fn($q) =>
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('company', fn($c) =>
                      $c->where('name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%")
                  )
            )
            ->when($request->category_id, fn($q, $categoryId) =>
                $q->whereHas('company', fn($c) =>
                    $c->where('company_category_id', $categoryId)
                )
            )
            ->with('company.companyCategory')
            ->limit(20)
            ->get();

        return $this->sendResponse(CompanyTickerResource::collection($tickers));
    }

    /**
     * GET /api/assets/categories
     */
    public function categories()
    {
        $categories = CompanyCategory::where('status', true)
            ->select('id', 'name', 'short_name', 'reference', 'color_hex', 'icon')
            ->get();

        return $this->sendResponse($categories);
    }

    /**
     * GET /api/assets/{companyTicker}
     */
    public function show(CompanyTicker $companyTicker)
    {
        return $this->sendResponse(
            new CompanyTickerResource($companyTicker->load('company.companyCategory'))
        );
    }
}
```

### 6.4 ConsolidatedController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Consolidated\StoreConsolidatedRequest;
use App\Http\Requests\Consolidated\UpdateConsolidatedRequest;
use App\Http\Resources\ConsolidatedResource;
use App\Models\Consolidated;
use Illuminate\Http\Request;

class ConsolidatedController extends BaseController
{
    /**
     * GET /api/consolidated
     */
    public function index(Request $request)
    {
        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->where('closed', false)
            ->with([
                'companyTicker.company.companyCategory',
                'account.bank',
            ])
            ->get();

        return $this->sendResponse(ConsolidatedResource::collection($consolidated));
    }

    /**
     * POST /api/consolidated
     */
    public function store(StoreConsolidatedRequest $request)
    {
        // Verifica se ja existe posicao para este ativo na conta
        $existing = Consolidated::where('account_id', $request->account_id)
            ->where('company_ticker_id', $request->company_ticker_id)
            ->first();

        if ($existing) {
            return $this->sendError(
                'Ja existe uma posicao para este ativo nesta conta.',
                [],
                409
            );
        }

        $data = $request->validated();
        $data['quantity_purchased'] = $data['quantity_current'];
        $data['total_purchased'] = $data['quantity_current'] * $data['average_purchase_price'];

        $consolidated = Consolidated::create($data);

        return $this->sendResponse(
            new ConsolidatedResource($consolidated->load('companyTicker.company.companyCategory', 'account.bank')),
            'Posicao registrada com sucesso.'
        );
    }

    /**
     * GET /api/consolidated/{consolidated}
     */
    public function show(Consolidated $consolidated)
    {
        $this->authorize('view', $consolidated);

        return $this->sendResponse(
            new ConsolidatedResource($consolidated->load('companyTicker.company.companyCategory', 'account.bank'))
        );
    }

    /**
     * PUT /api/consolidated/{consolidated}
     */
    public function update(UpdateConsolidatedRequest $request, Consolidated $consolidated)
    {
        $this->authorize('update', $consolidated);

        $data = $request->validated();

        if (isset($data['quantity_current']) && isset($data['average_purchase_price'])) {
            $data['total_purchased'] = $data['quantity_current'] * $data['average_purchase_price'];
        }

        $consolidated->update($data);

        return $this->sendResponse(
            new ConsolidatedResource($consolidated->fresh()->load('companyTicker.company.companyCategory', 'account.bank')),
            'Posicao atualizada com sucesso.'
        );
    }

    /**
     * DELETE /api/consolidated/{consolidated}
     */
    public function destroy(Consolidated $consolidated)
    {
        $this->authorize('delete', $consolidated);

        $consolidated->delete();

        return $this->sendResponse([], 'Posicao removida com sucesso.');
    }

    /**
     * GET /api/consolidated/summary
     */
    public function summary(Request $request)
    {
        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->where('closed', false)
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        $totalInvested = $consolidated->sum('total_purchased');
        $totalCurrent = $consolidated->sum('balance');
        $totalProfit = $totalCurrent - $totalInvested;
        $profitPercentage = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        $byCategory = $consolidated->groupBy(fn($item) =>
            $item->companyTicker->company->companyCategory->name
        )->map(fn($items) => [
            'count' => $items->count(),
            'invested' => round($items->sum('total_purchased'), 2),
            'current' => round($items->sum('balance'), 2),
            'profit' => round($items->sum('profit'), 2),
        ]);

        return $this->sendResponse([
            'total_invested' => round($totalInvested, 2),
            'total_current' => round($totalCurrent, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_percentage' => round($profitPercentage, 2),
            'assets_count' => $consolidated->count(),
            'by_category' => $byCategory,
        ]);
    }
}
```

### 6.5 PortfolioController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Portfolio\CrossingService;
use Illuminate\Http\Request;

class PortfolioController extends BaseController
{
    public function __construct(
        protected CrossingService $crossingService
    ) {}

    /**
     * GET /api/portfolios
     */
    public function index(Request $request)
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
     */
    public function store(StorePortfolioRequest $request)
    {
        $portfolio = $request->user()->portfolios()->create($request->validated());

        return $this->sendResponse(
            new PortfolioResource($portfolio),
            'Portfolio criado com sucesso.'
        );
    }

    /**
     * GET /api/portfolios/{portfolio}
     */
    public function show(Portfolio $portfolio)
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
     */
    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio)
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
     */
    public function destroy(Portfolio $portfolio)
    {
        $this->authorize('delete', $portfolio);

        $portfolio->compositions()->delete();
        $portfolio->delete();

        return $this->sendResponse([], 'Portfolio removido com sucesso.');
    }

    /**
     * GET /api/portfolios/{portfolio}/crossing
     */
    public function crossing(Portfolio $portfolio, Request $request)
    {
        $this->authorize('view', $portfolio);

        $crossingData = $this->crossingService->prepare(
            $portfolio,
            $request->user()
        );

        return $this->sendResponse([
            'portfolio' => new PortfolioResource($portfolio),
            'crossing' => $crossingData,
        ]);
    }
}
```

### 6.6 CompositionController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StoreCompositionRequest;
use App\Http\Requests\Portfolio\UpdateCompositionRequest;
use App\Http\Resources\CompositionResource;
use App\Models\Composition;
use App\Models\CompositionHistory;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class CompositionController extends BaseController
{
    /**
     * POST /api/portfolios/{portfolio}/compositions
     */
    public function store(StoreCompositionRequest $request, Portfolio $portfolio)
    {
        $this->authorize('update', $portfolio);

        $compositions = collect($request->compositions)->map(function ($item) use ($portfolio) {
            return Composition::firstOrCreate(
                [
                    'portfolio_id' => $portfolio->id,
                    'company_ticker_id' => $item['company_ticker_id'],
                ],
                [
                    'percentage' => $item['percentage'],
                ]
            );
        });

        return $this->sendResponse(
            CompositionResource::collection($compositions->load('companyTicker.company.companyCategory')),
            'Ativo(s) adicionado(s) ao portfolio.'
        );
    }

    /**
     * PUT /api/compositions/{composition}
     */
    public function update(UpdateCompositionRequest $request, Composition $composition)
    {
        $this->authorize('update', $composition->portfolio);

        $composition->update([
            'percentage' => $request->percentage,
        ]);

        return $this->sendResponse(
            new CompositionResource($composition->fresh()->load('companyTicker.company.companyCategory')),
            'Porcentagem atualizada com sucesso.'
        );
    }

    /**
     * PUT /api/compositions/batch
     */
    public function updateBatch(Request $request)
    {
        $request->validate([
            'compositions' => ['required', 'array'],
            'compositions.*.id' => ['required', 'integer', 'exists:portfolio_compositions,id'],
            'compositions.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($request->compositions as $item) {
            $composition = Composition::find($item['id']);
            $this->authorize('update', $composition->portfolio);
            $composition->update(['percentage' => $item['percentage']]);
        }

        return $this->sendResponse([], 'Composicoes atualizadas com sucesso.');
    }

    /**
     * DELETE /api/compositions/{composition}
     */
    public function destroy(Request $request, Composition $composition)
    {
        $this->authorize('update', $composition->portfolio);

        $request->validate([
            'save_to_history' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

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

### 6.7 Rotas API

```php
<?php

// routes/api.php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompositionController;
use App\Http\Controllers\Api\ConsolidatedController;
use App\Http\Controllers\Api\PortfolioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticacao
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'google']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

/*
|--------------------------------------------------------------------------
| Rotas Autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Accounts
    Route::apiResource('accounts', AccountController::class);

    // Assets (busca de ativos)
    Route::get('assets/search', [AssetController::class, 'search']);
    Route::get('assets/categories', [AssetController::class, 'categories']);
    Route::get('assets/{companyTicker}', [AssetController::class, 'show']);

    // Consolidated (posicoes)
    Route::get('consolidated/summary', [ConsolidatedController::class, 'summary']);
    Route::apiResource('consolidated', ConsolidatedController::class);

    // Portfolios
    Route::apiResource('portfolios', PortfolioController::class);
    Route::get('portfolios/{portfolio}/crossing', [PortfolioController::class, 'crossing']);

    // Compositions
    Route::post('portfolios/{portfolio}/compositions', [CompositionController::class, 'store']);
    Route::put('compositions/batch', [CompositionController::class, 'updateBatch']);
    Route::put('compositions/{composition}', [CompositionController::class, 'update']);
    Route::delete('compositions/{composition}', [CompositionController::class, 'destroy']);
});
```

---

## 7. Services e Helpers

### 7.1 CrossingService

```php
<?php

namespace App\Services\Portfolio;

use App\Helpers\PortfolioHelper;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Collection;

class CrossingService
{
    /**
     * Prepara dados de comparacao ideal vs real
     */
    public function prepare(Portfolio $portfolio, User $user): array
    {
        $compositions = $portfolio->compositions()
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        $accountIds = $user->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->where('closed', false)
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        $history = $portfolio->compositionHistories()
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        return $this->buildCrossingData($compositions, $consolidated, $history, $portfolio);
    }

    protected function buildCrossingData(
        Collection $compositions,
        Collection $consolidated,
        Collection $history,
        Portfolio $portfolio
    ): array {
        $crossingData = [];
        $processedTickers = [];

        // 1. Processa composicoes (portfolio ideal)
        foreach ($compositions as $composition) {
            $tickerId = $composition->company_ticker_id;
            $processedTickers[$tickerId] = true;

            $position = $consolidated->firstWhere('company_ticker_id', $tickerId);

            $crossingData[] = $this->buildCompositionItem($composition, $position, $portfolio);
        }

        // 2. Processa consolidados no historico (desmontar posicao)
        foreach ($consolidated as $position) {
            $tickerId = $position->company_ticker_id;

            if (isset($processedTickers[$tickerId])) {
                continue;
            }

            $historyItem = $history->firstWhere('company_ticker_id', $tickerId);

            if ($historyItem) {
                $crossingData[] = $this->buildUnwindItem($position, $historyItem);
                $processedTickers[$tickerId] = true;
            }
        }

        // 3. Ordena por categoria e ticker
        usort($crossingData, fn($a, $b) =>
            strcmp($a['sort_key'] ?? '', $b['sort_key'] ?? '')
        );

        return $crossingData;
    }

    protected function buildCompositionItem($composition, $position, Portfolio $portfolio): array
    {
        $ticker = $composition->companyTicker;
        $company = $ticker->company;
        $category = $company->companyCategory;
        $lastPrice = $ticker->last_price ?? 0;
        $netBalance = $position ? $position->balance : 0;

        $toBuyQuantity = PortfolioHelper::calculateToBuyQuantity(
            $composition->percentage,
            $portfolio->target_value,
            $netBalance,
            $lastPrice
        );

        return [
            'ticker_id' => $ticker->id,
            'composition_id' => $composition->id,
            'ticker' => $ticker->code,
            'name' => $company->name,
            'category' => $category->name,
            'category_reference' => $category->reference,
            'color_hex' => $category->color_hex,

            'ideal_percentage' => (float) $composition->percentage,

            'has_position' => $position !== null,
            'quantity_current' => (float) ($position?->quantity_current ?? 0),
            'total_purchased' => (float) ($position?->total_purchased ?? 0),
            'average_purchase_price' => (float) ($position?->average_purchase_price ?? 0),
            'balance' => (float) ($position?->balance ?? 0),
            'profit' => (float) ($position?->profit ?? 0),
            'profit_percentage' => (float) ($position?->profit_percentage ?? 0),

            'last_price' => (float) $lastPrice,
            'last_price_updated' => $ticker->last_price_updated,

            'to_buy_quantity' => $toBuyQuantity,
            'to_buy_formatted' => PortfolioHelper::formatToBuyQuantity($toBuyQuantity),

            'status' => $position ? 'positioned' : 'not_positioned',

            'sort_key' => "{$category->reference}_{$ticker->code}",
        ];
    }

    protected function buildUnwindItem($position, $historyItem): array
    {
        $ticker = $position->companyTicker;
        $company = $ticker->company;
        $category = $company->companyCategory;

        return [
            'ticker_id' => $ticker->id,
            'composition_id' => null,
            'ticker' => $ticker->code,
            'name' => $company->name,
            'category' => $category->name,
            'category_reference' => $category->reference,
            'color_hex' => $category->color_hex,

            'ideal_percentage' => 0,
            'was_percentage' => (float) $historyItem->percentage,
            'removal_reason' => $historyItem->reason,
            'removed_at' => $historyItem->deleted_at,

            'has_position' => true,
            'quantity_current' => (float) $position->quantity_current,
            'total_purchased' => (float) $position->total_purchased,
            'average_purchase_price' => (float) $position->average_purchase_price,
            'balance' => (float) $position->balance,
            'profit' => (float) $position->profit,
            'profit_percentage' => (float) $position->profit_percentage,

            'last_price' => (float) ($ticker->last_price ?? 0),

            'to_buy_quantity' => '-',
            'to_buy_formatted' => '-',

            'status' => 'unwind_position',

            'sort_key' => "Z_{$category->reference}_{$ticker->code}",
        ];
    }
}
```

### 7.2 PortfolioHelper

```php
<?php

namespace App\Helpers;

class PortfolioHelper
{
    /**
     * Calcula quantidade a comprar para atingir a meta
     */
    public static function calculateToBuyQuantity(
        float $idealPercentage,
        float $targetValue,
        float $currentBalance,
        ?float $lastPrice
    ): int|string|null {
        if ($lastPrice === null || $lastPrice <= 0) {
            return null;
        }

        if ($idealPercentage <= 0) {
            return 0;
        }

        $targetForAsset = ($idealPercentage * $targetValue) / 100;
        $toBuy = ($targetForAsset - $currentBalance) / $lastPrice;

        if ($toBuy > 0) {
            return (int) floor($toBuy);
        }

        return 0;
    }

    /**
     * Formata quantidade a comprar
     */
    public static function formatToBuyQuantity(int|string|null $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        if ($quantity === '-') {
            return '-';
        }

        if ($quantity > 0) {
            return "{$quantity} cotas";
        }

        return "0 cotas";
    }
}
```

---

## 8. Regras de Negocio

### 8.1 Regras de Account

| Regra | Descricao |
|-------|-----------|
| **Propriedade** | Uma account pertence a um unico usuario |
| **Default** | Apenas uma account pode ser default por usuario |
| **Exclusao** | Nao pode excluir account com posicoes ativas |

### 8.2 Regras de Consolidated

| Regra | Descricao |
|-------|-----------|
| **Unicidade** | Apenas uma posicao por ativo por conta |
| **Escopo** | Validar que a account pertence ao usuario |
| **Calculo** | `total_purchased = quantity_current * average_purchase_price` |

### 8.3 Regras de Portfolio

| Regra | Descricao |
|-------|-----------|
| **Propriedade** | Um portfolio pertence a um unico usuario |
| **Nome** | Maximo 80 caracteres |
| **Valores** | `month_value` e `target_value` >= 0 |
| **Soft Delete** | Portfolios removidos marcados com `deleted_at` |

### 8.4 Regras de Composicao

| Regra | Descricao |
|-------|-----------|
| **Unicidade** | Um ativo aparece apenas uma vez por portfolio |
| **Porcentagem** | Entre 0 e 100 |
| **Total** | Soma das porcentagens pode ser != 100% (feedback visual) |
| **Historico** | Ao remover, pode salvar no historico com motivo |

### 8.5 Regras de Crossing

| Status | Condicao |
|--------|----------|
| `positioned` | Ativo na composicao E tem posicao consolidada |
| `not_positioned` | Ativo na composicao MAS sem posicao |
| `unwind_position` | Ativo removido da composicao MAS ainda tem posicao |

### 8.6 Calculo de "Quanto Comprar"

```
objetivo_ativo = (percentual_ideal x valor_objetivo) / 100
valor_atual = saldo_atual
a_comprar = (objetivo_ativo - valor_atual) / ultimo_preco
resultado = floor(a_comprar) se > 0, senao 0
```

---

## 9. Fases de Implementacao

### Fase 1: Setup Inicial

**Objetivos:**
- Criar projeto Laravel 12
- Instalar dependencias (Sanctum, Google API Client)
- Configurar banco de dados
- Configurar CORS para React Native

**Tarefas:**
- [ ] `composer create-project laravel/laravel datagrana-portfolio`
- [ ] `composer require laravel/sanctum google/apiclient`
- [ ] Configurar `.env` (DB, Google, Sanctum)
- [ ] Publicar config do Sanctum
- [ ] Configurar CORS em `config/cors.php`

---

### Fase 2: Autenticacao Google OAuth

**Dependencias:** Fase 1

**Objetivos:**
- Implementar login via Google
- Criar/atualizar usuario automaticamente
- Gerar tokens Sanctum

**Tarefas:**
- [ ] Criar migration `users`
- [ ] Criar Model `User` com HasApiTokens
- [ ] Criar `GoogleAuthService`
- [ ] Criar `GoogleAuthRequest`
- [ ] Criar `AuthController`
- [ ] Criar `UserResource`
- [ ] Configurar rotas de auth
- [ ] Testar fluxo completo

---

### Fase 3: Core (Banks e Accounts)

**Dependencias:** Fase 2

**Objetivos:**
- Estrutura de bancos/corretoras
- CRUD de contas do usuario

**Tarefas:**
- [ ] Criar migration `banks`
- [ ] Criar migration `accounts`
- [ ] Criar Models `Bank` e `Account`
- [ ] Criar `BankSeeder`
- [ ] Criar Form Requests (Store/Update Account)
- [ ] Criar `AccountResource`
- [ ] Criar `AccountPolicy`
- [ ] Criar `AccountController`
- [ ] Configurar rotas

---

### Fase 4: Companies (Categorias e Tickers)

**Dependencias:** Fase 3

**Objetivos:**
- Estrutura de categorias de ativos
- Estrutura de empresas e tickers

**Tarefas:**
- [ ] Criar migration `company_category`
- [ ] Criar migration `companies`
- [ ] Criar migration `company_tickers`
- [ ] Criar Models (CompanyCategory, Company, CompanyTicker)
- [ ] Criar `CompanyCategorySeeder`
- [ ] Criar `CompanyTickerResource`
- [ ] Criar `AssetController`
- [ ] Configurar rotas

---

### Fase 5: Consolidated (Posicoes Reais)

**Dependencias:** Fase 4

**Objetivos:**
- Registro de compras de ativos
- Calculo de balance/profit

**Tarefas:**
- [ ] Criar migration `consolidated`
- [ ] Criar Model `Consolidated` com accessors
- [ ] Criar Form Requests (Store/Update Consolidated)
- [ ] Criar `ConsolidatedResource`
- [ ] Criar `ConsolidatedPolicy`
- [ ] Criar `ConsolidatedController`
- [ ] Configurar rotas
- [ ] Testar calculos

---

### Fase 6: Portfolio (Carteiras Ideais)

**Dependencias:** Fase 5

**Objetivos:**
- CRUD de portfolios
- CRUD de composicoes

**Tarefas:**
- [ ] Criar migration `portfolios`
- [ ] Criar migration `portfolio_compositions`
- [ ] Criar migration `portfolio_composition_histories`
- [ ] Criar Models (Portfolio, Composition, CompositionHistory)
- [ ] Criar Form Requests
- [ ] Criar Resources
- [ ] Criar `PortfolioPolicy`
- [ ] Criar `PortfolioController`
- [ ] Criar `CompositionController`
- [ ] Configurar rotas

---

### Fase 7: Crossing (Comparacao Ideal vs Real)

**Dependencias:** Fase 6

**Objetivos:**
- Implementar logica de crossing
- Calcular "quanto comprar"

**Tarefas:**
- [ ] Criar `PortfolioHelper`
- [ ] Criar `CrossingService`
- [ ] Implementar endpoint `crossing`
- [ ] Testar calculos
- [ ] Testar cenarios (positioned, not_positioned, unwind)

---

### Fase 8: Testes e Documentacao

**Dependencias:** Fase 7

**Objetivos:**
- Testes automatizados
- Documentacao da API

**Tarefas:**
- [ ] Criar testes de autenticacao
- [ ] Criar testes de accounts
- [ ] Criar testes de consolidated
- [ ] Criar testes de portfolio
- [ ] Criar testes de crossing
- [ ] Documentar endpoints (Postman/Insomnia)
- [ ] Testar fluxo completo

---

## 10. Dependencias e Pacotes

### 10.1 Composer

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "google/apiclient": "^2.0"
    },
    "require-dev": {
        "laravel/pint": "^1.0",
        "phpunit/phpunit": "^11.0"
    }
}
```

### 10.2 Instalacao

```bash
# Criar projeto
composer create-project laravel/laravel datagrana-portfolio

# Instalar Sanctum
composer require laravel/sanctum

# Instalar Google API Client
composer require google/apiclient

# Publicar configuracoes
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Gerar chave
php artisan key:generate
```

### 10.3 Variaveis de Ambiente

```env
APP_NAME="DataGrana Portfolio"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=datagrana_portfolio
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

---

## Resumo Executivo

O **DataGrana Portfolio** e uma API REST focada em gestao de carteiras de investimento em **Renda Variavel** com as seguintes caracteristicas:

**Fluxo do Usuario:**
1. Login com Google (cria usuario se nao existe)
2. Criar uma Account (conta na corretora)
3. Cadastrar compras de ativos (posicoes consolidadas)
4. Criar Portfolio com ativos e percentuais
5. Visualizar comparacao (Crossing)

**Entidades Principais:**
- User → Account → Consolidated
- CompanyCategory → Company → CompanyTicker
- User → Portfolio → Composition

**Endpoints Principais:**
- `POST /api/auth/google` - Login
- `GET/POST /api/accounts` - Contas
- `GET/POST /api/consolidated` - Posicoes
- `GET/POST /api/portfolios` - Carteiras
- `GET /api/portfolios/{id}/crossing` - Comparacao
- `GET /api/assets/search` - Buscar ativos

**Stack:**
- Backend: Laravel 12 + Sanctum
- Banco: MySQL/MariaDB
- Consumidor: React Native + Expo

**Padroes Aplicados:**
- BaseController com `sendResponse`/`sendError`
- Form Requests para validacao
- API Resources para transformacao
- Policies para autorizacao
- Services para logica complexa
