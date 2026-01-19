<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'API is running',
            ]);
    }
}
