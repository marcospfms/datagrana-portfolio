<?php

namespace Tests\Feature\Portfolio;

use App\Models\Composition;
use App\Models\Portfolio;
use Tests\TestCase;

class CompositionUpdateBatchTest extends TestCase
{
    public function test_can_update_batch(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $compositionA = Composition::factory()->forPortfolio($portfolio)->create(['percentage' => 10]);
        $compositionB = Composition::factory()->forPortfolio($portfolio)->create(['percentage' => 20]);

        $response = $this->putJson('/api/compositions/batch', [
            'compositions' => [
                ['id' => $compositionA->id, 'percentage' => 15],
                ['id' => $compositionB->id, 'percentage' => 25],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Composicoes atualizadas com sucesso.',
            ]);

        $this->assertDatabaseHas('compositions', [
            'id' => $compositionA->id,
            'percentage' => 15,
        ]);

        $this->assertDatabaseHas('compositions', [
            'id' => $compositionB->id,
            'percentage' => 25,
        ]);
    }

    public function test_cannot_update_batch_with_other_user_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherComposition = Composition::factory()->create(['percentage' => 10]);

        $response = $this->putJson('/api/compositions/batch', [
            'compositions' => [
                ['id' => $otherComposition->id, 'percentage' => 15],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }
}
