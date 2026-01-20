<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
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
}
