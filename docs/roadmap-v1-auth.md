# Roadmap V1 - Setup + Autenticacao Google OAuth

> Fase inicial: implementacao da autenticacao via Google OAuth com Sanctum no projeto existente.

---

## Contexto do Projeto

Este roadmap assume que o projeto **datagrana-portfolio** ja existe e esta configurado:

- **Laravel 12** ja instalado e configurado
- **Sanctum** ja instalado (migrations publicadas)
- **Banco de dados compartilhado** com `datagrana-web` (mesmo database)
- **API-only**: Ignora rotas web, Fortify, Breeze e Inertia (mantidos instalados para uso futuro)
- **Migrations copiadas** do datagrana-web (ja executadas no banco)

### Estrategia de Migracao

```
datagrana-web (atual) → sera deprecated
                ↓
datagrana-portfolio (novo) → substitui completamente
```

O **datagrana-portfolio** duplicara Models, Controllers e Services do `datagrana-web`, mas focando exclusivamente em API REST para consumo mobile.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Setup do Projeto](#3-setup-do-projeto)
4. [Estrutura de Arquivos](#4-estrutura-de-arquivos)
5. [Migration e Model](#5-migration-e-model)
6. [Service de Autenticacao](#6-service-de-autenticacao)
7. [Controller e Requests](#7-controller-e-requests)
8. [Resource](#8-resource)
9. [Rotas](#9-rotas)
10. [Casos de Teste](#10-casos-de-teste)
11. [Checklist de Implementacao](#11-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar o sistema de autenticacao completo usando Google OAuth **client-side**:

### Fluxo de Autenticacao (Client-Side OAuth)

```
1. App React Native inicia login Google localmente (SDK nativo)
   ↓
2. Google SDK no app abre tela de login
   ↓
3. Usuario faz login no Google
   ↓
4. Google retorna id_token para o APP
   ↓
5. App envia id_token para API Laravel (POST /api/auth/google)
   ↓
6. Backend valida id_token com Google (verifyIdToken)
   ↓
7. Backend cria/atualiza usuario no banco
   ↓
8. Backend retorna Bearer token Sanctum para o app
```

**Importante:**
- ❌ Nao ha callback/redirect server-side
- ❌ Nao usa OAuth code flow
- ✅ Apenas aceita `id_token` ja emitido pelo Google
- ✅ Backend apenas valida token e retorna Sanctum token

**Entregaveis:**
- Autenticacao Google OAuth funcional (client-side)
- Endpoints: login, me, logout, logout-all
- Testes automatizados
- Integracao com banco existente

---

## 2. Dependencias

### 2.1 Pacotes Composer

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

### 2.2 Servicos Externos

- **Google Cloud Console**: Credenciais OAuth 2.0
- **MySQL/MariaDB**: Banco de dados

---

## 3. Setup do Projeto

### 3.1 Verificar Instalacao

O projeto ja existe. Verifique se as dependencias necessarias estao instaladas:

```bash
cd datagrana-portfolio

# Verificar pacotes instalados
composer show laravel/sanctum
composer show google/apiclient

# Se algum pacote estiver faltando, instale:
composer require google/apiclient
```

### 3.2 Configurar .env

**Importante:** O banco de dados e **compartilhado** com `datagrana-web`.

```env
APP_NAME="DataGrana Portfolio"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de Dados (MESMO banco do datagrana-web)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=datagrana
DB_USERNAME=root
DB_PASSWORD=

# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### 3.3 Configurar config/services.php

```php
<?php

return [
    // ... outras configs

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
];
```

### 3.4 Configurar CORS (config/cors.php)

Para mobile com Bearer token, a configuracao pode ser simplificada, mas mantemos completa para uso futuro:

```php
<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Em producao, especificar domínios
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Mantido para uso futuro web
];
```

### 3.5 Configurar Sanctum (config/sanctum.php)

Mantemos configuracao completa (stateful/CSRF) para uso futuro, mas mobile usa apenas Bearer tokens:

```php
<?php

return [
    // Para mobile, pode ser vazio, mas mantemos para futuro uso web
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),

    'guard' => ['web'],

    'expiration' => null, // Tokens nao expiram (ou defina em minutos)

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

**Nota:** Mobile apps usam apenas `Authorization: Bearer {token}` - nao precisam de cookies/CSRF.

---

## 4. Estrutura de Arquivos

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BaseController.php
│   │       └── AuthController.php
│   ├── Requests/
│   │   └── Auth/
│   │       └── GoogleAuthRequest.php
│   └── Resources/
│       └── UserResource.php
├── Models/
│   └── User.php
└── Services/
    └── Auth/
        └── GoogleAuthService.php

database/
└── migrations/
    └── 0001_01_01_000000_create_users_table.php

routes/
└── api.php

tests/
└── Feature/
    └── Auth/
        ├── GoogleAuthTest.php
        └── LogoutTest.php
```

---

## 5. Migration e Model

### 5.1 Migration: users

**Arquivo:** `database/migrations/0001_01_01_000000_create_users_table.php`

**Importante:** Esta migration ja foi executada no banco compartilhado. Ela esta aqui como **referencia** e sera copiada do `datagrana-web`. Quando rodar `php artisan migrate`, nao sera executada novamente (tabela `migrations` ja registra).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('photo', 500)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable para OAuth
            $table->rememberToken();
            $table->string('google_id')->nullable(); // String ao inves de text
            $table->timestamps();

            $table->index('google_id');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

**Nota:** Copie esta migration exatamente como esta no `datagrana-web` para manter compatibilidade.

### 5.2 Model: User

**Arquivo:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'google_id',
        'photo',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Verifica se usuario esta ativo
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Scope para usuarios ativos
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
```

### 5.3 Factory: UserFactory

**Arquivo:** `database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => true,
            'google_id' => fake()->unique()->numerify('############'),
            'photo' => fake()->imageUrl(200, 200, 'people'),
        ];
    }

    /**
     * Usuario inativo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * Usuario sem google_id
     */
    public function withoutGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => null,
        ]);
    }
}
```

---

## 6. Service de Autenticacao

### 6.1 GoogleAuthService

**Arquivo:** `app/Services/Auth/GoogleAuthService.php`

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
     *
     * @param string $idToken Token recebido do cliente
     * @return array|null Dados do usuario ou null se invalido
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if (!$payload) {
                Log::warning('Google token verification returned empty payload');
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Encontra ou cria usuario baseado nos dados do Google
     *
     * @param array $googleData Dados retornados por verifyIdToken
     * @return User Usuario encontrado ou criado
     */
    public function findOrCreateUser(array $googleData): User
    {
        // Busca por google_id primeiro, depois por email
        $user = User::where('google_id', $googleData['google_id'])
            ->orWhere('email', $googleData['email'])
            ->first();

        if ($user) {
            // Atualiza dados do Google
            $user->update([
                'google_id' => $googleData['google_id'],
                'photo' => $googleData['photo'] ?? $user->photo,
                'name' => $googleData['name'] ?? $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            Log::info('User updated via Google OAuth', ['user_id' => $user->id]);
        } else {
            // Cria novo usuario
            $user = User::create([
                'google_id' => $googleData['google_id'],
                'email' => $googleData['email'],
                'name' => $googleData['name'] ?? 'Usuario',
                'photo' => $googleData['photo'],
                'email_verified_at' => now(),
                'status' => true,
            ]);

            Log::info('New user created via Google OAuth', ['user_id' => $user->id]);
        }

        return $user;
    }
}
```

---

## 7. Controller e Requests

### 7.1 BaseController

**Arquivo:** `app/Http/Controllers/Api/BaseController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    /**
     * Resposta de sucesso padronizada
     *
     * @param mixed $result Dados a retornar
     * @param string|null $message Mensagem opcional
     * @return JsonResponse
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
     * Resposta de erro padronizada
     *
     * @param string $error Mensagem de erro
     * @param array $errorMessages Detalhes do erro
     * @param int $code Codigo HTTP
     * @return JsonResponse
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

### 7.2 GoogleAuthRequest

**Arquivo:** `app/Http/Requests/Auth/GoogleAuthRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    /**
     * Determina se o usuario esta autorizado
     */
    public function authorize(): bool
    {
        return true; // Rota publica
    }

    /**
     * Regras de validacao
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'min:100'],
        ];
    }

    /**
     * Mensagens de erro customizadas
     */
    public function messages(): array
    {
        return [
            'id_token.required' => 'O token do Google e obrigatorio.',
            'id_token.string' => 'O token do Google deve ser uma string.',
            'id_token.min' => 'O token do Google parece ser invalido.',
        ];
    }
}
```

### 7.3 AuthController

**Arquivo:** `app/Http/Controllers/Api/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    /**
     * POST /api/auth/google
     *
     * Autentica usuario via Google OAuth
     */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        // Verifica token com Google
        $googleData = $this->googleAuthService->verifyIdToken($request->id_token);

        if (!$googleData) {
            return $this->sendError(
                'Token do Google invalido ou expirado.',
                [],
                401
            );
        }

        // Encontra ou cria usuario
        $user = $this->googleAuthService->findOrCreateUser($googleData);

        // Verifica se usuario esta ativo
        if (!$user->isActive()) {
            return $this->sendError(
                'Sua conta esta desativada. Entre em contato com o suporte.',
                [],
                403
            );
        }

        // Revoga tokens anteriores e cria novo
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
     *
     * Retorna dados do usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * POST /api/auth/logout
     *
     * Revoga o token atual
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Logout realizado com sucesso.');
    }

    /**
     * POST /api/auth/logout-all
     *
     * Revoga todos os tokens do usuario
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->sendResponse([], 'Logout de todos os dispositivos realizado.');
    }
}
```

---

## 8. Resource

### 8.1 UserResource

**Arquivo:** `app/Http/Resources/UserResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transforma o resource em array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'photo' => $this->photo,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## 9. Rotas

### 9.1 Arquivo de Rotas API

**Arquivo:** `routes/api.php`

```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Autenticacao
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Rotas publicas
    Route::post('/google', [AuthController::class, 'google'])
        ->name('auth.google');

    // Rotas autenticadas
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])
            ->name('auth.me');

        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        Route::post('/logout-all', [AuthController::class, 'logoutAll'])
            ->name('auth.logout-all');
    });
});

/*
|--------------------------------------------------------------------------
| Rota de Health Check
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

### 10.1 Teste Base (TestCase)

**Arquivo:** `tests/TestCase.php`

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper para criar usuario autenticado com token
     */
    protected function createAuthenticatedUser(array $attributes = []): array
    {
        $user = \App\Models\User::factory()->create($attributes);
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Helper para headers de autenticacao
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }
}
```

### 10.2 GoogleAuthTest

**Arquivo:** `tests/Feature/Auth/GoogleAuthTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_can_login_with_valid_google_token(): void
    {
        // Mock do GoogleAuthService
        $mockGoogleData = [
            'google_id' => '123456789',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'photo' => 'https://example.com/photo.jpg',
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturn(User::factory()->create([
                'google_id' => $mockGoogleData['google_id'],
                'email' => $mockGoogleData['email'],
                'name' => $mockGoogleData['name'],
            ]));

        $this->app->instance(GoogleAuthService::class, $mock);

        // Faz request
        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid_google_token_here',
        ]);

        // Assertions
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'photo',
                        'status',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                ],
                'message' => 'Login realizado com sucesso.',
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_login_with_invalid_google_token(): void
    {
        // Mock retornando null (token invalido)
        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn(null);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'invalid_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Token do Google invalido ou expirado.',
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_login_without_id_token(): void
    {
        $response = $this->postJson('/api/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }

    /**
     * @test
     */
    public function test_cannot_login_with_empty_id_token(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'id_token' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }

    /**
     * @test
     */
    public function test_cannot_login_when_user_is_inactive(): void
    {
        $inactiveUser = User::factory()->inactive()->create();

        $mockGoogleData = [
            'google_id' => $inactiveUser->google_id,
            'email' => $inactiveUser->email,
            'name' => $inactiveUser->name,
            'photo' => $inactiveUser->photo,
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturn($inactiveUser);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid_token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Sua conta esta desativada. Entre em contato com o suporte.',
            ]);
    }

    /**
     * @test
     */
    public function test_creates_new_user_on_first_login(): void
    {
        $mockGoogleData = [
            'google_id' => 'new_google_id_123',
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'photo' => 'https://example.com/new-photo.jpg',
            'email_verified' => true,
        ];

        // Nao usar mock - testar integracao real
        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($data) {
                return User::create([
                    'google_id' => $data['google_id'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'photo' => $data['photo'],
                    'email_verified_at' => now(),
                    'status' => true,
                ]);
            });

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid_token_for_new_user',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verifica que usuario foi criado
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => 'new_google_id_123',
        ]);
    }

    /**
     * @test
     */
    public function test_revokes_previous_tokens_on_login(): void
    {
        $user = User::factory()->create();

        // Cria token anterior
        $oldToken = $user->createToken('old-token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $mockGoogleData = [
            'google_id' => $user->google_id,
            'email' => $user->email,
            'name' => $user->name,
            'photo' => $user->photo,
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')->andReturn($user);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid_token',
        ]);

        $response->assertStatus(200);

        // Deve ter apenas 1 token (o novo)
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Token antigo nao deve funcionar
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $oldToken,
        ])->assertStatus(401);
    }
}
```

### 10.3 MeEndpointTest

**Arquivo:** `tests/Feature/Auth/MeEndpointTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_get_authenticated_user_data(): void
    {
        $auth = $this->createAuthenticatedUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/auth/me', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'photo',
                        'status',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ]);
    }

    /**
     * @test
     */
    public function test_cannot_get_user_data_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_cannot_get_user_data_with_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid_token_here',
        ]);

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_cannot_get_user_data_with_revoked_token(): void
    {
        $auth = $this->createAuthenticatedUser();

        // Revoga o token
        $auth['user']->tokens()->delete();

        $response = $this->getJson('/api/auth/me', $this->authHeaders($auth['token']));

        $response->assertStatus(401);
    }
}
```

### 10.4 LogoutTest

**Arquivo:** `tests/Feature/Auth/LogoutTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_can_logout_current_device(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/auth/logout', [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout realizado com sucesso.',
            ]);

        // Token deve estar revogado
        $this->getJson('/api/auth/me', $this->authHeaders($auth['token']))
            ->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_can_logout_all_devices(): void
    {
        $auth = $this->createAuthenticatedUser();

        // Cria tokens adicionais (simulando outros dispositivos)
        $token2 = $auth['user']->createToken('device-2')->plainTextToken;
        $token3 = $auth['user']->createToken('device-3')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $response = $this->postJson('/api/auth/logout-all', [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout de todos os dispositivos realizado.',
            ]);

        // Todos os tokens devem estar revogados
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Nenhum token deve funcionar
        $this->getJson('/api/auth/me', $this->authHeaders($auth['token']))
            ->assertStatus(401);
        $this->getJson('/api/auth/me', $this->authHeaders($token2))
            ->assertStatus(401);
        $this->getJson('/api/auth/me', $this->authHeaders($token3))
            ->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_cannot_logout_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_cannot_logout_all_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout-all');

        $response->assertStatus(401);
    }
}
```

### 10.5 HealthCheckTest

**Arquivo:** `tests/Feature/HealthCheckTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * @test
     */
    public function test_health_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'API is running',
            ]);
    }
}
```

### 10.6 Rodar Testes

```bash
# Rodar todos os testes
php artisan test

# Rodar apenas testes de auth
php artisan test --filter=Auth

# Rodar com coverage
php artisan test --coverage

# Rodar teste especifico
php artisan test --filter=test_can_login_with_valid_google_token
```

---

## 11. Checklist de Implementacao

### 11.1 Setup

- [ ] Verificar instalacao do `google/apiclient` (instalar se necessario)
- [ ] Configurar `.env` com credenciais Google OAuth
- [ ] Verificar `config/services.php` (adicionar Google)
- [ ] Revisar `config/cors.php` (manter completo)
- [ ] Revisar `config/sanctum.php` (manter completo)

### 11.2 Database

- [ ] Copiar migration `users` do datagrana-web (se ainda nao copiada)
- [ ] Verificar com `php artisan migrate` (nao deve criar nada novo)
- [ ] Criar `UserFactory` (duplicar do datagrana-web se existir)

### 11.3 Backend

- [ ] Criar `BaseController`
- [ ] Criar `GoogleAuthService`
- [ ] Criar `GoogleAuthRequest`
- [ ] Criar `AuthController`
- [ ] Criar `UserResource`
- [ ] Configurar rotas em `routes/api.php`

### 11.4 Testes

- [ ] Criar `GoogleAuthTest`
- [ ] Criar `MeEndpointTest`
- [ ] Criar `LogoutTest`
- [ ] Criar `HealthCheckTest`
- [ ] Rodar `php artisan test` - todos passando

### 11.5 Validacao Final

- [ ] Testar endpoint `/api/health`
- [ ] Testar login com token Google real (Postman/Insomnia)
- [ ] Testar `/api/auth/me` com Bearer token
- [ ] Testar `/api/auth/logout`
- [ ] Testar `/api/auth/logout-all`

---

## Endpoints da V1

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/health` | Nao | Health check |
| POST | `/api/auth/google` | Nao | Login com Google |
| GET | `/api/auth/me` | Sim | Dados do usuario |
| POST | `/api/auth/logout` | Sim | Logout device atual |
| POST | `/api/auth/logout-all` | Sim | Logout todos devices |

---

## Proxima Fase

Apos completar a V1, prosseguir para:
- **V2 - Core**: Banks e Accounts (contas em corretoras)
