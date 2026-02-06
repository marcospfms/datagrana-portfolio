<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
use App\Services\SubscriptionLimitService;
use Tests\TestCase;

class PortfolioDestroyTest extends TestCase
{
    public function test_can_delete_own_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $response = $this->deleteJson("/api/portfolios/{$portfolio->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio removido com sucesso.',
            ]);

        $this->assertSoftDeleted('portfolios', [
            'id' => $portfolio->id,
        ]);
    }

    public function test_cannot_delete_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->create();

        $response = $this->deleteJson("/api/portfolios/{$portfolio->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_cannot_delete_without_authentication(): void
    {
        $portfolio = Portfolio::factory()->create();

        $response = $this->deleteJson("/api/portfolios/{$portfolio->id}");

        $response->assertStatus(401);
    }

    public function test_can_delete_portfolio_outside_edit_limit(): void
    {
        $auth = $this->createAuthenticatedUser();

        $oldPortfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'created_at' => now()->subDays(2),
        ]);
        $newPortfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'created_at' => now(),
        ]);

        $limitService = app(SubscriptionLimitService::class);
        $this->assertFalse($limitService->canEditPortfolio($auth['user'], $newPortfolio));

        $response = $this->deleteJson("/api/portfolios/{$newPortfolio->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertSoftDeleted('portfolios', ['id' => $newPortfolio->id]);
        $this->assertDatabaseHas('portfolios', ['id' => $oldPortfolio->id]);
    }
}
