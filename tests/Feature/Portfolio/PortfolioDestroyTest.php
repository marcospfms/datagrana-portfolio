<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
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
}
