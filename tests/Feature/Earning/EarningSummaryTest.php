<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Consolidated;
use App\Models\Earning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_summary(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->subDays(2),
            'net_value' => 10,
            'gross_value' => 12,
            'tax' => 2,
        ]);

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->subDays(1),
            'net_value' => 5,
            'gross_value' => 6,
            'tax' => 1,
        ]);

        $response = $this->getJson(
            '/api/earnings/summary',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.total_net', '15.00000000')
            ->assertJsonPath('data.total_gross', '18.00000000')
            ->assertJsonPath('data.total_tax', '3.00000000');
    }

    public function test_summary_respects_date_range(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->subMonths(2)->startOfMonth(),
            'net_value' => 10,
            'gross_value' => 12,
            'tax' => 2,
        ]);

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->startOfMonth(),
            'net_value' => 5,
            'gross_value' => 6,
            'tax' => 1,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $response = $this->getJson(
            "/api/earnings/summary?from={$from}&to={$to}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.total_net', '5.00000000')
            ->assertJsonPath('data.total_gross', '6.00000000')
            ->assertJsonPath('data.total_tax', '1.00000000');
    }

    public function test_can_get_grouped_by_month(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->subMonths(1)->startOfMonth(),
            'net_value' => 10,
            'gross_value' => 12,
            'tax' => 2,
        ]);

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->startOfMonth(),
            'net_value' => 5,
            'gross_value' => 6,
            'tax' => 1,
        ]);

        $response = $this->getJson(
            '/api/earnings/grouped?group=month',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_grouped_respects_date_range(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->subMonths(2)->startOfMonth(),
            'net_value' => 10,
            'gross_value' => 12,
            'tax' => 2,
        ]);

        Earning::factory()->forConsolidated($consolidated)->create([
            'date' => now()->startOfMonth(),
            'net_value' => 5,
            'gross_value' => 6,
            'tax' => 1,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $response = $this->getJson(
            "/api/earnings/grouped?group=month&from={$from}&to={$to}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
