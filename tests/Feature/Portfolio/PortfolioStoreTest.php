<?php

namespace Tests\Feature\Portfolio;

use Tests\TestCase;

class PortfolioStoreTest extends TestCase
{
    public function test_can_create_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Meu Portfolio',
            'month_value' => 1000.00,
            'target_value' => 50000.00,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio criado com sucesso.',
            ])
            ->assertJsonPath('data.name', 'Meu Portfolio');

        $this->assertDatabaseHas('portfolios', [
            'user_id' => $auth['user']->id,
            'name' => 'Meu Portfolio',
        ]);
    }

    public function test_name_is_required(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'month_value' => 1000.00,
            'target_value' => 50000.00,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_values_are_required(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Sem valores',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month_value', 'target_value']);
    }

    public function test_values_cannot_be_negative(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Test',
            'month_value' => -100,
            'target_value' => -1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month_value', 'target_value']);
    }
}
