<?php

namespace Tests\Feature\Earning;

use App\Models\EarningType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningTypeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_earning_types(): void
    {
        $auth = $this->createAuthenticatedUser();

        EarningType::factory()->count(3)->create();

        $response = $this->getJson('/api/earning-types', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/earning-types');

        $response->assertStatus(401);
    }
}
