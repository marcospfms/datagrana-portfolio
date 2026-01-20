<?php

namespace Tests\Feature\Portfolio;

use App\Models\Composition;
use App\Models\Portfolio;
use Tests\TestCase;

class CompositionUpdateTest extends TestCase
{
    public function test_can_update_own_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create([
            'percentage' => 10,
        ]);

        $response = $this->putJson("/api/compositions/{$composition->id}", [
            'percentage' => 22.5,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.percentage', '22.50');

        $this->assertDatabaseHas('compositions', [
            'id' => $composition->id,
            'percentage' => 22.5,
        ]);
    }

    public function test_cannot_update_other_user_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $composition = Composition::factory()->create();

        $response = $this->putJson("/api/compositions/{$composition->id}", [
            'percentage' => 10,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_percentage_must_be_valid_on_update(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $composition = Composition::factory()->forPortfolio($portfolio)->create();

        $response = $this->putJson("/api/compositions/{$composition->id}", [
            'percentage' => -1,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['percentage']);
    }
}
