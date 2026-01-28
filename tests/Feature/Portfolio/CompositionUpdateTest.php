<?php

namespace Tests\Feature\Portfolio;

use App\Models\Composition;
use App\Models\Portfolio;
use Illuminate\Support\Carbon;
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

    public function test_cannot_update_composition_outside_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $baseDate = Carbon::now()->subDays(20);
        $compositions = Composition::factory()
            ->count(11)
            ->forPortfolio($portfolio)
            ->sequence(fn ($sequence) => [
                'created_at' => $baseDate->copy()->addDays($sequence->index),
            ])
            ->create();

        $oldest = $compositions->first();
        $newest = $compositions->last();

        $blockedResponse = $this->putJson("/api/compositions/{$newest->id}", [
            'percentage' => 12.5,
        ], $this->authHeaders($auth['token']));

        $blockedResponse->assertStatus(403);

        $allowedResponse = $this->putJson("/api/compositions/{$oldest->id}", [
            'percentage' => 12.5,
        ], $this->authHeaders($auth['token']));

        $allowedResponse->assertStatus(200)
            ->assertJsonPath('data.percentage', '12.50');
    }
}
