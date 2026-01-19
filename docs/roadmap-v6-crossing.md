# Roadmap V6 - Crossing (Comparacao Ideal vs Real)

> Comparacao entre portfolio ideal e posicoes reais consolidadas.

---

## Indice

1. [Objetivo da Fase](#1-objetivo-da-fase)
2. [Dependencias](#2-dependencias)
3. [Estrutura de Arquivos](#3-estrutura-de-arquivos)
4. [Helper](#4-helper)
5. [Service](#5-service)
6. [Atualizar Controller](#6-atualizar-controller)
7. [Rotas](#7-rotas)
8. [Casos de Teste](#8-casos-de-teste)
9. [Checklist de Implementacao](#9-checklist-de-implementacao)

---

## 1. Objetivo da Fase

Implementar a funcionalidade de **Crossing** (cruzamento):

- Comparar composicao ideal vs posicoes reais
- Calcular quanto comprar de cada ativo
- Identificar ativos a desmontar (no historico mas com posicao)
- Ordenar por categoria e ticker

**Entregaveis:**
- `PortfolioHelper` com calculos
- `CrossingService` com logica de comparacao
- Endpoint `/api/portfolios/{id}/crossing`
- Testes automatizados

---

## 2. Dependencias

**Requer:** V5 (Portfolio) completa

**Tabelas necessarias:**
- `portfolios`
- `portfolio_compositions`
- `portfolio_composition_histories`
- `consolidated`
- `company_tickers`

---

## 3. Estrutura de Arquivos

```
app/
├── Helpers/
│   └── PortfolioHelper.php
├── Services/
│   └── Portfolio/
│       └── CrossingService.php
└── Http/
    └── Controllers/
        └── Api/
            └── PortfolioController.php (atualizar)

tests/
└── Feature/
    └── Portfolio/
        └── CrossingTest.php
```

---

## 4. Helper

### 4.1 PortfolioHelper

**Arquivo:** `app/Helpers/PortfolioHelper.php`

```php
<?php

namespace App\Helpers;

class PortfolioHelper
{
    /**
     * Calcula quantidade a comprar para atingir a meta
     *
     * Formula:
     * objetivo_ativo = (percentual_ideal x valor_objetivo) / 100
     * valor_atual = saldo_atual
     * a_comprar = (objetivo_ativo - valor_atual) / ultimo_preco
     * resultado = floor(a_comprar) se > 0, senao 0
     *
     * @param float $idealPercentage Percentual ideal do ativo (0-100)
     * @param float $targetValue Valor objetivo total do portfolio
     * @param float $currentBalance Saldo atual do ativo
     * @param float|null $lastPrice Ultimo preco do ativo
     * @return int|string|null Quantidade a comprar ou null se sem preco
     */
    public static function calculateToBuyQuantity(
        float $idealPercentage,
        float $targetValue,
        float $currentBalance,
        ?float $lastPrice
    ): int|string|null {
        // Sem preco = impossivel calcular
        if ($lastPrice === null || $lastPrice <= 0) {
            return null;
        }

        // Percentual zero = nada a comprar
        if ($idealPercentage <= 0) {
            return 0;
        }

        // Calcula valor objetivo para este ativo
        $targetForAsset = ($idealPercentage * $targetValue) / 100;

        // Calcula quanto falta
        $toBuy = ($targetForAsset - $currentBalance) / $lastPrice;

        // Se positivo, arredonda para baixo
        if ($toBuy > 0) {
            return (int) floor($toBuy);
        }

        return 0;
    }

    /**
     * Formata quantidade a comprar para exibicao
     *
     * @param int|string|null $quantity Quantidade calculada
     * @return string|null Texto formatado
     */
    public static function formatToBuyQuantity(int|string|null $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        if ($quantity === '-') {
            return '-';
        }

        if ($quantity === 0) {
            return '0 cotas';
        }

        if ($quantity === 1) {
            return '1 cota';
        }

        return "{$quantity} cotas";
    }

    /**
     * Calcula valor monetario a comprar
     *
     * @param int $quantity Quantidade de cotas
     * @param float|null $lastPrice Preco por cota
     * @return float|null Valor total
     */
    public static function calculateToBuyValue(int $quantity, ?float $lastPrice): ?float
    {
        if ($lastPrice === null || $lastPrice <= 0) {
            return null;
        }

        return round($quantity * $lastPrice, 2);
    }

    /**
     * Determina status do ativo no crossing
     *
     * @param bool $inComposition Esta na composicao do portfolio?
     * @param bool $hasPosition Tem posicao consolidada?
     * @param bool $inHistory Esta no historico de remocoes?
     * @return string Status: positioned, not_positioned, unwind_position, extra_position
     */
    public static function determineStatus(
        bool $inComposition,
        bool $hasPosition,
        bool $inHistory
    ): string {
        if ($inComposition && $hasPosition) {
            return 'positioned'; // Ideal e tem posicao
        }

        if ($inComposition && !$hasPosition) {
            return 'not_positioned'; // Ideal mas sem posicao
        }

        if ($inHistory && $hasPosition) {
            return 'unwind_position'; // Foi removido mas ainda tem posicao
        }

        if (!$inComposition && $hasPosition) {
            return 'extra_position'; // Tem posicao mas nao esta no portfolio
        }

        return 'unknown';
    }
}
```

---

## 5. Service

### 5.1 CrossingService

**Arquivo:** `app/Services/Portfolio/CrossingService.php`

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
     *
     * @param Portfolio $portfolio Portfolio a comparar
     * @param User $user Usuario dono do portfolio
     * @return array Dados de crossing
     */
    public function prepare(Portfolio $portfolio, User $user): array
    {
        // Carrega composicoes do portfolio
        $compositions = $portfolio->compositions()
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        // Carrega posicoes consolidadas do usuario
        $accountIds = $user->accounts()->pluck('id');
        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->where('closed', false)
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        // Carrega historico de remocoes
        $history = $portfolio->compositionHistories()
            ->with(['companyTicker.company.companyCategory'])
            ->get();

        return $this->buildCrossingData($compositions, $consolidated, $history, $portfolio);
    }

    /**
     * Constroi array de dados para crossing
     */
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

        // 2. Processa consolidados no historico (ativos a desmontar)
        foreach ($consolidated as $position) {
            $tickerId = $position->company_ticker_id;

            // Ja processado como composicao
            if (isset($processedTickers[$tickerId])) {
                continue;
            }

            // Verifica se esta no historico
            $historyItem = $history->firstWhere('company_ticker_id', $tickerId);

            if ($historyItem) {
                $crossingData[] = $this->buildUnwindItem($position, $historyItem);
                $processedTickers[$tickerId] = true;
            }
        }

        // 3. Ordena por categoria e ticker
        usort($crossingData, function ($a, $b) {
            return strcmp($a['sort_key'] ?? '', $b['sort_key'] ?? '');
        });

        return $crossingData;
    }

    /**
     * Constroi item de composicao
     */
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

        $toBuyValue = is_int($toBuyQuantity)
            ? PortfolioHelper::calculateToBuyValue($toBuyQuantity, $lastPrice)
            : null;

        $status = PortfolioHelper::determineStatus(
            inComposition: true,
            hasPosition: $position !== null,
            inHistory: false
        );

        return [
            // Identificacao
            'ticker_id' => $ticker->id,
            'composition_id' => $composition->id,
            'ticker' => $ticker->code,
            'name' => $company->name,

            // Categoria
            'category' => $category->name,
            'category_reference' => $category->reference,
            'color_hex' => $category->color_hex,

            // Alocacao ideal
            'ideal_percentage' => (float) $composition->percentage,

            // Posicao atual
            'has_position' => $position !== null,
            'quantity_current' => (float) ($position?->quantity_current ?? 0),
            'total_purchased' => (float) ($position?->total_purchased ?? 0),
            'average_purchase_price' => (float) ($position?->average_purchase_price ?? 0),
            'balance' => (float) $netBalance,
            'profit' => (float) ($position?->profit ?? 0),
            'profit_percentage' => (float) ($position?->profit_percentage ?? 0),

            // Preco
            'last_price' => (float) $lastPrice,
            'last_price_updated' => $ticker->last_price_updated?->toISOString(),

            // Calculo de compra
            'to_buy_quantity' => $toBuyQuantity,
            'to_buy_formatted' => PortfolioHelper::formatToBuyQuantity($toBuyQuantity),
            'to_buy_value' => $toBuyValue,

            // Status
            'status' => $status,

            // Ordenacao
            'sort_key' => "A_{$category->reference}_{$ticker->code}",
        ];
    }

    /**
     * Constroi item de desmonte (ativo removido mas com posicao)
     */
    protected function buildUnwindItem($position, $historyItem): array
    {
        $ticker = $position->companyTicker;
        $company = $ticker->company;
        $category = $company->companyCategory;

        return [
            // Identificacao
            'ticker_id' => $ticker->id,
            'composition_id' => null,
            'ticker' => $ticker->code,
            'name' => $company->name,

            // Categoria
            'category' => $category->name,
            'category_reference' => $category->reference,
            'color_hex' => $category->color_hex,

            // Alocacao (foi removido)
            'ideal_percentage' => 0,
            'was_percentage' => (float) $historyItem->percentage,
            'removal_reason' => $historyItem->reason,
            'removed_at' => $historyItem->created_at->toISOString(),

            // Posicao atual (a desmontar)
            'has_position' => true,
            'quantity_current' => (float) $position->quantity_current,
            'total_purchased' => (float) $position->total_purchased,
            'average_purchase_price' => (float) $position->average_purchase_price,
            'balance' => (float) $position->balance,
            'profit' => (float) $position->profit,
            'profit_percentage' => (float) $position->profit_percentage,

            // Preco
            'last_price' => (float) ($ticker->last_price ?? 0),
            'last_price_updated' => $ticker->last_price_updated?->toISOString(),

            // Calculo de compra (nao se aplica)
            'to_buy_quantity' => '-',
            'to_buy_formatted' => '-',
            'to_buy_value' => null,

            // Status
            'status' => 'unwind_position',

            // Ordenacao (Z para ficar no final)
            'sort_key' => "Z_{$category->reference}_{$ticker->code}",
        ];
    }

    /**
     * Calcula totais do crossing
     */
    public function calculateTotals(array $crossingData, Portfolio $portfolio): array
    {
        $totalInvested = 0;
        $totalCurrent = 0;
        $totalToBuy = 0;
        $countPositioned = 0;
        $countNotPositioned = 0;
        $countUnwind = 0;

        foreach ($crossingData as $item) {
            $totalInvested += $item['total_purchased'] ?? 0;
            $totalCurrent += $item['balance'] ?? 0;

            if (is_numeric($item['to_buy_value'])) {
                $totalToBuy += $item['to_buy_value'];
            }

            switch ($item['status']) {
                case 'positioned':
                    $countPositioned++;
                    break;
                case 'not_positioned':
                    $countNotPositioned++;
                    break;
                case 'unwind_position':
                    $countUnwind++;
                    break;
            }
        }

        $totalProfit = $totalCurrent - $totalInvested;
        $profitPercentage = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        return [
            'total_invested' => round($totalInvested, 2),
            'total_current' => round($totalCurrent, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_percentage' => round($profitPercentage, 2),
            'total_to_buy' => round($totalToBuy, 2),
            'target_value' => (float) $portfolio->target_value,
            'month_value' => (float) $portfolio->month_value,
            'progress_percentage' => $portfolio->target_value > 0
                ? round(($totalCurrent / $portfolio->target_value) * 100, 2)
                : 0,
            'counts' => [
                'total' => count($crossingData),
                'positioned' => $countPositioned,
                'not_positioned' => $countNotPositioned,
                'unwind' => $countUnwind,
            ],
        ];
    }
}
```

---

## 6. Atualizar Controller

### 6.1 PortfolioController (adicionar metodo)

**Arquivo:** `app/Http/Controllers/Api/PortfolioController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Portfolio\CrossingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends BaseController
{
    public function __construct(
        protected CrossingService $crossingService
    ) {}

    // ... outros metodos (index, store, show, update, destroy) ...

    /**
     * GET /api/portfolios/{portfolio}/crossing
     *
     * Retorna comparacao ideal vs real
     */
    public function crossing(Portfolio $portfolio, Request $request): JsonResponse
    {
        $this->authorize('view', $portfolio);

        $portfolio->load([
            'compositions.companyTicker.company.companyCategory',
            'compositionHistories.companyTicker.company.companyCategory',
        ]);

        $crossingData = $this->crossingService->prepare($portfolio, $request->user());
        $totals = $this->crossingService->calculateTotals($crossingData, $portfolio);

        return $this->sendResponse([
            'portfolio' => new PortfolioResource($portfolio),
            'crossing' => $crossingData,
            'totals' => $totals,
        ]);
    }
}
```

---

## 7. Rotas

### 7.1 Atualizar routes/api.php

```php
// Adicionar no grupo de portfolios:

Route::get('/portfolios/{portfolio}/crossing', [PortfolioController::class, 'crossing'])
    ->name('portfolios.crossing');
```

---

## 8. Casos de Teste

### 8.1 CrossingTest

**Arquivo:** `tests/Feature/Portfolio/CrossingTest.php`

```php
<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Composition;
use App\Models\CompositionHistory;
use App\Models\Consolidated;
use App\Models\Portfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossingTest extends TestCase
{
    use RefreshDatabase;

    private function createFullSetup($user): array
    {
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = CompanyCategory::factory()->create(['name' => 'Acoes', 'reference' => 'Acoes']);
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
            'last_price' => 35.00,
        ]);

        return compact('account', 'category', 'company', 'ticker');
    }

    /**
     * @test
     */
    public function test_can_get_crossing_data(): void
    {
        $auth = $this->createAuthenticatedUser();
        $setup = $this->createFullSetup($auth['user']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $setup['ticker']->id,
            'percentage' => 25.00, // 25% de 10000 = 2500
        ]);

        Consolidated::factory()->create([
            'account_id' => $setup['account']->id,
            'company_ticker_id' => $setup['ticker']->id,
            'quantity_current' => 50,
            'average_purchase_price' => 30.00,
            'total_purchased' => 1500.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portfolio',
                    'crossing' => [
                        '*' => [
                            'ticker_id',
                            'ticker',
                            'name',
                            'category',
                            'ideal_percentage',
                            'has_position',
                            'quantity_current',
                            'balance',
                            'to_buy_quantity',
                            'status',
                        ],
                    ],
                    'totals' => [
                        'total_invested',
                        'total_current',
                        'total_profit',
                        'total_to_buy',
                        'counts',
                    ],
                ],
            ]);

        // Verifica valores calculados
        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('PETR4', $crossingItem['ticker']);
        $this->assertEquals(25.00, $crossingItem['ideal_percentage']);
        $this->assertTrue($crossingItem['has_position']);
        $this->assertEquals('positioned', $crossingItem['status']);
    }

    /**
     * @test
     */
    public function test_calculates_to_buy_quantity_correctly(): void
    {
        $auth = $this->createAuthenticatedUser();
        $setup = $this->createFullSetup($auth['user']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        // 25% de 10000 = 2500 alvo
        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $setup['ticker']->id,
            'percentage' => 25.00,
        ]);

        // Posicao atual: 50 cotas x 35 = 1750
        Consolidated::factory()->create([
            'account_id' => $setup['account']->id,
            'company_ticker_id' => $setup['ticker']->id,
            'quantity_current' => 50,
            'average_purchase_price' => 30.00,
            'total_purchased' => 1500.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        // A comprar: (2500 - 1750) / 35 = 21.42 -> floor = 21
        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals(21, $crossingItem['to_buy_quantity']);
        $this->assertEquals('21 cotas', $crossingItem['to_buy_formatted']);
    }

    /**
     * @test
     */
    public function test_identifies_not_positioned_assets(): void
    {
        $auth = $this->createAuthenticatedUser();
        $setup = $this->createFullSetup($auth['user']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $setup['ticker']->id,
            'percentage' => 25.00,
        ]);

        // Sem posicao consolidada

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertFalse($crossingItem['has_position']);
        $this->assertEquals('not_positioned', $crossingItem['status']);
        $this->assertEquals(0, $crossingItem['quantity_current']);
    }

    /**
     * @test
     */
    public function test_identifies_unwind_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $setup = $this->createFullSetup($auth['user']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        // Ativo no historico (foi removido da composicao)
        CompositionHistory::factory()->create([
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $setup['ticker']->id,
            'percentage' => 15.00,
            'reason' => 'Empresa cortou dividendos',
        ]);

        // Mas ainda tem posicao
        Consolidated::factory()->create([
            'account_id' => $setup['account']->id,
            'company_ticker_id' => $setup['ticker']->id,
            'quantity_current' => 100,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('unwind_position', $crossingItem['status']);
        $this->assertEquals(0, $crossingItem['ideal_percentage']);
        $this->assertEquals(15.00, $crossingItem['was_percentage']);
        $this->assertEquals('Empresa cortou dividendos', $crossingItem['removal_reason']);
    }

    /**
     * @test
     */
    public function test_calculates_totals_correctly(): void
    {
        $auth = $this->createAuthenticatedUser();
        $setup = $this->createFullSetup($auth['user']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
            'month_value' => 1000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $setup['ticker']->id,
            'percentage' => 50.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $setup['account']->id,
            'company_ticker_id' => $setup['ticker']->id,
            'quantity_current' => 100,
            'average_purchase_price' => 30.00,
            'total_purchased' => 3000.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $totals = $response->json('data.totals');
        $this->assertEquals(3000.00, $totals['total_invested']);
        $this->assertEquals(3500.00, $totals['total_current']); // 100 x 35
        $this->assertEquals(500.00, $totals['total_profit']); // 3500 - 3000
        $this->assertEquals(10000.00, $totals['target_value']);
        $this->assertEquals(1000.00, $totals['month_value']);
        $this->assertEquals(35.00, $totals['progress_percentage']); // 3500/10000
    }

    /**
     * @test
     */
    public function test_returns_null_to_buy_when_no_price(): void
    {
        $auth = $this->createAuthenticatedUser();

        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'last_price' => null, // Sem preco
        ]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertNull($crossingItem['to_buy_quantity']);
        $this->assertNull($crossingItem['to_buy_formatted']);
    }

    /**
     * @test
     */
    public function test_cannot_get_crossing_for_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherPortfolio = Portfolio::factory()->create();

        $response = $this->getJson(
            "/api/portfolios/{$otherPortfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function test_cannot_get_crossing_without_authentication(): void
    {
        $portfolio = Portfolio::factory()->create();

        $response = $this->getJson("/api/portfolios/{$portfolio->id}/crossing");

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function test_crossing_sorts_by_category_and_ticker(): void
    {
        $auth = $this->createAuthenticatedUser();

        $categoryAcoes = CompanyCategory::factory()->create(['name' => 'Acoes', 'reference' => 'Acoes']);
        $categoryFiis = CompanyCategory::factory()->create(['name' => 'FIIs', 'reference' => 'FII']);

        $companyAcao = Company::factory()->create(['company_category_id' => $categoryAcoes->id]);
        $companyFii = Company::factory()->create(['company_category_id' => $categoryFiis->id]);

        $tickerPetr = CompanyTicker::factory()->create(['company_id' => $companyAcao->id, 'code' => 'PETR4']);
        $tickerVale = CompanyTicker::factory()->create(['company_id' => $companyAcao->id, 'code' => 'VALE3']);
        $tickerHglg = CompanyTicker::factory()->create(['company_id' => $companyFii->id, 'code' => 'HGLG11']);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        Composition::factory()->forPortfolio($portfolio)->create(['company_ticker_id' => $tickerHglg->id]);
        Composition::factory()->forPortfolio($portfolio)->create(['company_ticker_id' => $tickerVale->id]);
        Composition::factory()->forPortfolio($portfolio)->create(['company_ticker_id' => $tickerPetr->id]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $tickers = collect($response->json('data.crossing'))->pluck('ticker')->toArray();

        // Acoes primeiro (PETR4, VALE3), depois FIIs (HGLG11)
        $this->assertEquals(['PETR4', 'VALE3', 'HGLG11'], $tickers);
    }
}
```

### 8.2 PortfolioHelperTest

**Arquivo:** `tests/Unit/Helpers/PortfolioHelperTest.php`

```php
<?php

namespace Tests\Unit\Helpers;

use App\Helpers\PortfolioHelper;
use PHPUnit\Framework\TestCase;

class PortfolioHelperTest extends TestCase
{
    /**
     * @test
     */
    public function test_calculates_to_buy_quantity(): void
    {
        // 25% de 10000 = 2500, atual = 1750, preco = 35
        // (2500 - 1750) / 35 = 21.42 -> floor = 21
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 1750.00, 35.00);

        $this->assertEquals(21, $result);
    }

    /**
     * @test
     */
    public function test_returns_zero_when_already_reached_target(): void
    {
        // 25% de 10000 = 2500, atual = 3000 (ja passou)
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 3000.00, 35.00);

        $this->assertEquals(0, $result);
    }

    /**
     * @test
     */
    public function test_returns_null_when_no_price(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 0.00, null);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function test_returns_null_when_price_is_zero(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 0.00, 0.00);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function test_returns_zero_when_percentage_is_zero(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(0.00, 10000.00, 0.00, 35.00);

        $this->assertEquals(0, $result);
    }

    /**
     * @test
     */
    public function test_formats_quantity_correctly(): void
    {
        $this->assertEquals('21 cotas', PortfolioHelper::formatToBuyQuantity(21));
        $this->assertEquals('1 cota', PortfolioHelper::formatToBuyQuantity(1));
        $this->assertEquals('0 cotas', PortfolioHelper::formatToBuyQuantity(0));
        $this->assertEquals('-', PortfolioHelper::formatToBuyQuantity('-'));
        $this->assertNull(PortfolioHelper::formatToBuyQuantity(null));
    }

    /**
     * @test
     */
    public function test_determines_status_correctly(): void
    {
        $this->assertEquals(
            'positioned',
            PortfolioHelper::determineStatus(inComposition: true, hasPosition: true, inHistory: false)
        );

        $this->assertEquals(
            'not_positioned',
            PortfolioHelper::determineStatus(inComposition: true, hasPosition: false, inHistory: false)
        );

        $this->assertEquals(
            'unwind_position',
            PortfolioHelper::determineStatus(inComposition: false, hasPosition: true, inHistory: true)
        );

        $this->assertEquals(
            'extra_position',
            PortfolioHelper::determineStatus(inComposition: false, hasPosition: true, inHistory: false)
        );
    }
}
```

---

## 9. Checklist de Implementacao

### 9.1 Backend

- [ ] Criar `app/Helpers/PortfolioHelper.php`
- [ ] Criar `app/Services/Portfolio/CrossingService.php`
- [ ] Atualizar `PortfolioController` com metodo `crossing`
- [ ] Adicionar injecao de `CrossingService` no controller
- [ ] Configurar rota `/api/portfolios/{id}/crossing`

### 9.2 Testes

- [ ] Criar `tests/Feature/Portfolio/CrossingTest.php`
- [ ] Criar `tests/Unit/Helpers/PortfolioHelperTest.php`
- [ ] Rodar `php artisan test` - todos passando

### 9.3 Validacao Final

- [ ] Testar `GET /api/portfolios/{id}/crossing` com posicoes
- [ ] Testar com ativos sem posicao (not_positioned)
- [ ] Testar com ativos no historico (unwind_position)
- [ ] Verificar calculos de to_buy_quantity
- [ ] Verificar ordenacao por categoria/ticker
- [ ] Verificar totais calculados

---

## Endpoint da V6

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| GET | `/api/portfolios/{id}/crossing` | Sim | Comparacao ideal vs real |

---

## Resposta do Endpoint

```json
{
  "success": true,
  "data": {
    "portfolio": {
      "id": 1,
      "name": "Meu Portfolio",
      "target_value": "10000.00",
      "month_value": "1000.00",
      "total_percentage": "100.00"
    },
    "crossing": [
      {
        "ticker_id": 1,
        "composition_id": 1,
        "ticker": "PETR4",
        "name": "Petrobras",
        "category": "Acoes",
        "color_hex": "#3B82F6",
        "ideal_percentage": 25.0,
        "has_position": true,
        "quantity_current": 50.0,
        "total_purchased": 1500.0,
        "balance": 1750.0,
        "profit": 250.0,
        "profit_percentage": 16.67,
        "last_price": 35.0,
        "to_buy_quantity": 21,
        "to_buy_formatted": "21 cotas",
        "to_buy_value": 735.0,
        "status": "positioned"
      }
    ],
    "totals": {
      "total_invested": 1500.0,
      "total_current": 1750.0,
      "total_profit": 250.0,
      "profit_percentage": 16.67,
      "total_to_buy": 735.0,
      "target_value": 10000.0,
      "month_value": 1000.0,
      "progress_percentage": 17.5,
      "counts": {
        "total": 1,
        "positioned": 1,
        "not_positioned": 0,
        "unwind": 0
      }
    }
  }
}
```

---

## Projeto Concluido!

Com a V6 completa, o projeto **DataGrana Portfolio** esta pronto com todas as funcionalidades:

1. **V1 - Auth**: Login Google OAuth + Sanctum
2. **V2 - Core**: Banks + Accounts
3. **V3 - Companies**: Categorias + Empresas + Tickers
4. **V4 - Consolidated**: Posicoes reais (compras)
5. **V5 - Portfolio**: Carteiras ideais + Composicoes
6. **V6 - Crossing**: Comparacao ideal vs real

**Fluxo completo do usuario:**
1. Login com Google
2. Criar conta na corretora
3. Registrar compras de ativos
4. Criar portfolio com alocacoes
5. Visualizar crossing (quanto comprar de cada ativo)
