# Services - Camada de Lógica de Negócio

**Objetivo**: Isolar lógica de negócio complexa dos Controllers, facilitando testes e reutilização.

---

## 📋 Índice

1. [Quando Usar Services](#quando-usar-services)
2. [Estrutura e Convenções](#estrutura-e-convenções)
3. [Exemplos Práticos](#exemplos-práticos)
4. [Dependency Injection](#dependency-injection)
5. [Testes de Services](#testes-de-services)
6. [Boas Práticas](#boas-práticas)

---

## Quando Usar Services

### ✅ USE Services quando:

1. **Lógica complexa** (>30 linhas)
2. **Múltiplas operações** no banco de dados
3. **Lógica reutilizável** em múltiplos controllers
4. **Operações transacionais** complexas
5. **Integração com APIs externas**
6. **Cálculos de negócio** complexos
7. **Processamento de dados** pesado

### ❌ NÃO use Services para:

1. **Operações CRUD simples** (use direto no Controller)
2. **Queries simples** (use Eloquent)
3. **Validação** (use Form Requests)
4. **Transformação de dados** (use Resources)

---

## Estrutura e Convenções

### Organização de Pastas

**Nota**: Hoje o projeto usa `app/Services` de forma plana. Subpastas sao opcionais e devem ser criadas apenas quando houver volume/necessidade clara.

```
app/Services/
├── Auth/
│   ├── AuthService.php
│   └── TwoFactorService.php
├── Billing/
│   ├── SubscriptionService.php
│   └── PaymentService.php
├── External/
│   ├── ApiClientService.php
│   └── WebhookService.php
└── Resource/
    ├── ResourceService.php
    └── ResourceImportService.php
```

### Nomenclatura

- **PascalCase**: `ResourceService`, `BillingService`
- **Sufixo `Service`**: Sempre terminar com `Service`
- **Verbos descritivos**: Métodos com verbos de ação (`create`, `process`, `calculate`)

---

## Exemplos Práticos

### 1. Service Básico

```php
<?php

namespace App\Services\Resource;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResourceService
{
    /**
     * Cria um novo recurso com validação de negócio
     */
    public function create(array $data, User $user): Resource
    {
        // Validação de regras de negócio
        $this->validateBusinessRules($data, $user);

        return DB::transaction(function () use ($data, $user) {
            // Criar recurso
            $resource = $user->resources()->create($data);

            // Operações adicionais
            $this->processRelatedData($resource, $data);
            $this->notifyStakeholders($resource);

            return $resource;
        });
    }

    /**
     * Atualiza recurso com lógica complexa
     */
    public function update(Resource $resource, array $data): Resource
    {
        $this->validateBusinessRules($data, $resource->user);

        return DB::transaction(function () use ($resource, $data) {
            $resource->update($data);

            $this->syncRelatedData($resource, $data);

            return $resource->fresh();
        });
    }

    /**
     * Deleta recurso com cleanup
     */
    public function delete(Resource $resource): bool
    {
        return DB::transaction(function () use ($resource) {
            // Cleanup de dados relacionados
            $this->cleanupRelatedData($resource);

            // Soft delete ou hard delete
            return $resource->delete();
        });
    }

    /**
     * Validações de regras de negócio
     */
    protected function validateBusinessRules(array $data, User $user): void
    {
        // Exemplo: verificar limites de assinatura
        if (!$user->canCreateResource()) {
            throw new \DomainException('Limite de recursos atingido');
        }

        // Exemplo: validar unicidade de negócio
        if ($this->isDuplicate($data, $user)) {
            throw new \DomainException('Recurso duplicado');
        }
    }

    protected function isDuplicate(array $data, User $user): bool
    {
        return Resource::where('user_id', $user->id)
            ->where('name', $data['name'])
            ->exists();
    }

    protected function processRelatedData(Resource $resource, array $data): void
    {
        // Processar dados relacionados
    }

    protected function syncRelatedData(Resource $resource, array $data): void
    {
        // Sincronizar dados relacionados
    }

    protected function cleanupRelatedData(Resource $resource): void
    {
        // Limpar dados relacionados
    }

    protected function notifyStakeholders(Resource $resource): void
    {
        // Notificar stakeholders
    }
}
```

---

### 2. Service com Múltiplas Responsabilidades

```php
<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Ativa assinatura para usuário
     */
    public function activate(
        User $user,
        SubscriptionPlan $plan,
        ?int $trialDays = null
    ): Subscription {
        // Cancelar assinatura ativa atual
        $this->cancelActive($user);

        return DB::transaction(function () use ($user, $plan, $trialDays) {
            $startsAt = now();
            $endsAt = $trialDays
                ? $startsAt->copy()->addDays($trialDays)
                : $startsAt->copy()->addMonth();

            // Criar snapshot do plano
            $subscription = $user->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'max_resources' => $plan->max_resources,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
            ]);

            // Inicializar contadores de uso
            $this->initializeUsage($subscription);

            // Evento de auditoria
            $this->logSubscriptionChange($user, 'activated', $subscription);

            return $subscription;
        });
    }

    /**
     * Cancela assinatura ativa do usuário
     */
    public function cancelActive(User $user): void
    {
        $activeSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->first();

        if ($activeSubscription) {
            $activeSubscription->update(['status' => 'canceled']);
            $this->logSubscriptionChange($user, 'canceled', $activeSubscription);
        }
    }

    /**
     * Verifica se usuário pode usar recurso (limite)
     */
    public function canUseResource(User $user, string $resourceType): bool
    {
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return false;
        }

        $usage = $this->getCurrentUsage($subscription);
        $limit = $subscription->{"max_{$resourceType}"};

        // Null = ilimitado
        if ($limit === null) {
            return true;
        }

        return $usage->{"used_{$resourceType}"} < $limit;
    }

    /**
     * Incrementa contador de uso
     */
    public function incrementUsage(User $user, string $resourceType): void
    {
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return;
        }

        $usage = $this->getCurrentUsage($subscription);
        $usage->increment("used_{$resourceType}");
    }

    /**
     * Reseta contadores mensais
     */
    public function resetMonthlyCounters(Subscription $subscription): void
    {
        $usage = $this->getCurrentUsage($subscription);

        $usage->update([
            'used_monthly_resources' => 0,
            // Resetar outros contadores mensais
        ]);
    }

    /**
     * Calcula métricas de uso
     */
    public function calculateUsageMetrics(Subscription $subscription): array
    {
        $usage = $this->getCurrentUsage($subscription);

        return [
            'resources' => [
                'used' => $usage->used_resources,
                'limit' => $subscription->max_resources,
                'percentage' => $this->calculatePercentage(
                    $usage->used_resources,
                    $subscription->max_resources
                ),
            ],
            // Outras métricas
        ];
    }

    /**
     * Inicializa registro de uso para nova assinatura
     */
    protected function initializeUsage(Subscription $subscription): SubscriptionUsage
    {
        return SubscriptionUsage::create([
            'user_subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'used_resources' => 0,
            // Inicializar outros contadores
        ]);
    }

    /**
     * Obtém registro de uso do período atual
     */
    protected function getCurrentUsage(Subscription $subscription): SubscriptionUsage
    {
        return SubscriptionUsage::firstOrCreate(
            [
                'user_subscription_id' => $subscription->id,
                'period_year' => now()->year,
                'period_month' => now()->month,
            ],
            [
                'user_id' => $subscription->user_id,
                'used_resources' => 0,
            ]
        );
    }

    protected function calculatePercentage(?int $used, ?int $limit): ?float
    {
        if ($limit === null || $limit === 0) {
            return null;
        }

        return round(($used / $limit) * 100, 2);
    }

    protected function logSubscriptionChange(
        User $user,
        string $action,
        Subscription $subscription
    ): void {
    }
}
```

---

### 3. Service para Integração Externa

```php
<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout = 30;
    protected int $cacheTtl = 3600; // 1 hora

    public function __construct()
    {
        $this->baseUrl = config('services.external_api.url');
        $this->apiKey = config('services.external_api.key');
    }

    /**
     * Busca dados com cache
     */
    public function fetchData(string $endpoint, array $params = []): array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($endpoint, $params) {
            return $this->makeRequest('GET', $endpoint, $params);
        });
    }

    /**
     * Envia dados (sem cache)
     */
    public function sendData(string $endpoint, array $data): array
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Faz requisição HTTP com retry
     */
    protected function makeRequest(
        string $method,
        string $endpoint,
        array $data = []
    ): array {
        try {
            $response = Http::timeout($this->timeout)
                ->retry(3, 1000) // 3 tentativas, 1s entre cada
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept' => 'application/json',
                ])
                ->{strtolower($method)}("{$this->baseUrl}/{$endpoint}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException(
                "API Error: {$response->status()} - {$response->body()}"
            );
        } catch (\Exception $e) {
            Log::error('External API Error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function getCacheKey(string $endpoint, array $params): string
    {
        return sprintf(
            'external_api:%s:%s',
            $endpoint,
            md5(json_encode($params))
        );
    }

    /**
     * Invalida cache de um endpoint
     */
    public function clearCache(string $endpoint, array $params = []): void
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);
        Cache::forget($cacheKey);
    }
}
```

---

## Dependency Injection

### Injetar Services em Controllers

```php
<?php

namespace App\Http\Controllers\Api;

use App\Services\Resource\ResourceService;
use App\Http\Requests\StoreResourceRequest;
use Illuminate\Http\JsonResponse;

class ResourceController extends BaseController
{
    public function __construct(
        protected ResourceService $resourceService
    ) {}

    public function store(StoreResourceRequest $request): JsonResponse
    {
        $resource = $this->resourceService->create(
            $request->validated(),
            $request->user()
        );

        return $this->sendResponse(
            new ResourceResource($resource),
            'Resource created successfully',
            201
        );
    }

    public function destroy(Resource $resource): JsonResponse
    {
        $this->resourceService->delete($resource);

        return $this->sendResponse([], 'Resource deleted successfully');
    }
}
```

---

## Testes de Services

### Test Unitário de Service

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Resource\ResourceService;
use App\Models\User;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResourceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ResourceService::class);
    }

    public function test_can_create_resource(): void
    {
        $user = User::factory()->create();
        $data = [
            'name' => 'Test Resource',
            'description' => 'Test Description',
        ];

        $resource = $this->service->create($data, $user);

        $this->assertInstanceOf(Resource::class, $resource);
        $this->assertEquals($data['name'], $resource->name);
        $this->assertEquals($user->id, $resource->user_id);
        $this->assertDatabaseHas('resources', [
            'name' => $data['name'],
            'user_id' => $user->id,
        ]);
    }

    public function test_cannot_create_duplicate_resource(): void
    {
        $user = User::factory()->create();
        $data = ['name' => 'Duplicate'];

        // Criar primeiro recurso
        $this->service->create($data, $user);

        // Tentar criar duplicado
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Recurso duplicado');

        $this->service->create($data, $user);
    }

    public function test_can_update_resource(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->for($user)->create();
        $newData = ['name' => 'Updated Name'];

        $updated = $this->service->update($resource, $newData);

        $this->assertEquals($newData['name'], $updated->name);
        $this->assertDatabaseHas('resources', [
            'id' => $resource->id,
            'name' => $newData['name'],
        ]);
    }

    public function test_can_delete_resource(): void
    {
        $user = User::factory()->create();
        $resource = Resource::factory()->for($user)->create();

        $result = $this->service->delete($resource);

        $this->assertTrue($result);
        $this->assertSoftDeleted('resources', ['id' => $resource->id]);
    }
}
```

---

## Boas Práticas

### 1. Single Responsibility

✅ **Um service, uma responsabilidade**
```php
// ✅ Correto - Foco em billing
class BillingService { }

// ✅ Correto - Foco em subscription
class SubscriptionService { }

// ❌ Evitar - Responsabilidades misturadas
class BillingAndSubscriptionAndNotificationService { }
```

---

### 2. Dependency Injection

✅ **Injetar dependências no construtor**
```php
// ✅ Correto
class ResourceService
{
    public function __construct(
        protected ExternalApiService $apiService,
        protected CacheService $cacheService
    ) {}
}

// ❌ Evitar - Instanciar manualmente
class ResourceService
{
    protected $apiService;

    public function __construct()
    {
        $this->apiService = new ExternalApiService();
    }
}
```

---

### 3. Return Types Explícitos

✅ **Sempre declarar tipos de retorno**
```php
// ✅ Correto
public function create(array $data, User $user): Resource
{
    // ...
}

// ❌ Evitar
public function create($data, $user)
{
    // ...
}
```

---

### 4. Exceptions Customizadas

✅ **Usar exceptions específicas**
```php
// app/Exceptions/ResourceLimitExceededException.php
class ResourceLimitExceededException extends \DomainException
{
    public static function forUser(User $user, string $resourceType): self
    {
        return new self(
            "User {$user->id} exceeded limit for {$resourceType}"
        );
    }
}

// Uso
if (!$user->canCreateResource()) {
    throw ResourceLimitExceededException::forUser($user, 'resources');
}
```

---

### 5. Transações para Múltiplas Operações

✅ **Envolver operações múltiplas em transação**
```php
public function create(array $data, User $user): Resource
{
    return DB::transaction(function () use ($data, $user) {
        $resource = $user->resources()->create($data);
        $this->processRelated($resource);
        $this->updateCounters($user);
        return $resource;
    });
}
```

---

### 6. Log de Operações Importantes

✅ **Logar operações críticas**
```php
public function delete(Resource $resource): bool
{
    Log::info('Deleting resource', [
        'resource_id' => $resource->id,
        'user_id' => $resource->user_id,
    ]);

    $result = DB::transaction(fn () => $resource->delete());

    if ($result) {
        Log::info('Resource deleted successfully', [
            'resource_id' => $resource->id,
        ]);
    }

    return $result;
}
```

---

## 📝 Checklist de Qualidade

- [ ] Service tem uma única responsabilidade
- [ ] Métodos têm type hints completos
- [ ] Lógica complexa está isolada
- [ ] Operações múltiplas usam transações
- [ ] Exceptions customizadas quando apropriado
- [ ] Operações críticas são logadas
- [ ] Dependencies são injetadas
- [ ] Service tem testes unitários
- [ ] Documentação PHPDoc nos métodos públicos
- [ ] Validações de negócio estão no service (não no controller)

---

**Nota**: Services são agnósticos ao domínio e podem ser aplicados em qualquer projeto Laravel, independente de tabelas ou modelos específicos.
