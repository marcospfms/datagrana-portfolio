<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PortfolioUpdateTest extends TestCase
{
    public function test_can_update_own_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $response = $this->putJson("/api/portfolios/{$portfolio->id}", [
            'name' => 'Atualizado',
            'month_value' => 2000.00,
            'target_value' => 60000.00,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Atualizado');

        $this->assertDatabaseHas('portfolios', [
            'id' => $portfolio->id,
            'name' => 'Atualizado',
        ]);
    }

    public function test_cannot_update_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->create();

        $response = $this->putJson("/api/portfolios/{$portfolio->id}", [
            'name' => 'Nao permitido',
            'month_value' => 100,
            'target_value' => 1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_update_requires_all_fields(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $response = $this->putJson("/api/portfolios/{$portfolio->id}", [
            'name' => '',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'month_value', 'target_value']);
    }

    public function test_cannot_update_portfolio_outside_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $baseDate = Carbon::now()->subDays(10);

        $portfolios = Portfolio::factory()
            ->count(2)
            ->forUser($auth['user'])
            ->sequence(fn ($sequence) => [
                'created_at' => $baseDate->copy()->addDays($sequence->index),
            ])
            ->create();

        $oldest = $portfolios->first();
        $newest = $portfolios->last();

        $blockedResponse = $this->putJson("/api/portfolios/{$newest->id}", [
            'name' => 'Bloqueada',
            'month_value' => 1000,
            'target_value' => 50000,
        ], $this->authHeaders($auth['token']));

        $blockedResponse->assertStatus(403);

        $allowedResponse = $this->putJson("/api/portfolios/{$oldest->id}", [
            'name' => 'Permitida',
            'month_value' => 1200,
            'target_value' => 60000,
        ], $this->authHeaders($auth['token']));

        $allowedResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Permitida');
    }
}
