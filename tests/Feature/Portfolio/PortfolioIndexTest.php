<?php

namespace Tests\Feature\Portfolio;

use App\Models\Portfolio;
use Tests\TestCase;

class PortfolioIndexTest extends TestCase
{
    public function test_can_list_own_portfolios(): void
    {
        $auth = $this->createAuthenticatedUser();

        Portfolio::factory()->count(3)->forUser($auth['user'])->create();
        Portfolio::factory()->count(2)->create();

        $response = $this->getJson('/api/portfolios', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_portfolios_by_name(): void
    {
        $auth = $this->createAuthenticatedUser();

        Portfolio::factory()->forUser($auth['user'])->create(['name' => 'Carteira Dividendos']);
        Portfolio::factory()->forUser($auth['user'])->create(['name' => 'Carteira Growth']);

        $response = $this->getJson('/api/portfolios?name=Dividendos', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Carteira Dividendos');
    }

    public function test_cannot_list_portfolios_without_authentication(): void
    {
        $response = $this->getJson('/api/portfolios');

        $response->assertStatus(401);
    }
}
