<?php

namespace Tests\Feature\Portfolio;

use App\Models\Composition;
use App\Models\Portfolio;
use Tests\TestCase;

class CompositionDestroyTest extends TestCase
{
    public function test_can_remove_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create();

        $response = $this->deleteJson(
            "/api/compositions/{$composition->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Composicao removida com sucesso.',
            ]);

        $this->assertDatabaseMissing('compositions', [
            'id' => $composition->id,
        ]);
    }

    public function test_can_save_to_history_on_remove(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create([
            'percentage' => 15.00,
        ]);

        $response = $this->deleteJson("/api/compositions/{$composition->id}", [
            'save_to_history' => true,
            'reason' => 'Ativo saiu da carteira',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $this->assertDatabaseHas('composition_histories', [
            'portfolio_id' => $portfolio->id,
            'treasure_id' => $composition->treasure_id,
            'company_ticker_id' => $composition->company_ticker_id,
            'percentage' => 15.00,
            'reason' => 'Ativo saiu da carteira',
        ]);
    }

    public function test_cannot_remove_other_user_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $composition = Composition::factory()->create();

        $response = $this->deleteJson(
            "/api/compositions/{$composition->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    public function test_cannot_remove_without_authentication(): void
    {
        $composition = Composition::factory()->create();

        $response = $this->deleteJson("/api/compositions/{$composition->id}");

        $response->assertStatus(401);
    }
}
