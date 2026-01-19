<?php

namespace Tests\Feature\Asset;

use App\Models\CompanyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_categories(): void
    {
        CompanyCategory::factory()->count(3)->create(['status' => true]);
        CompanyCategory::factory()->count(2)->inactive()->create();

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/categories', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'short_name',
                        'reference',
                        'color_hex',
                        'icon',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_list_categories_without_authentication(): void
    {
        $response = $this->getJson('/api/companies/categories');

        $response->assertStatus(401);
    }

    public function test_categories_are_ordered_by_name(): void
    {
        CompanyCategory::factory()->create(['name' => 'Zebra']);
        CompanyCategory::factory()->create(['name' => 'Alpha']);
        CompanyCategory::factory()->create(['name' => 'Beta']);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/categories', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha', 'Beta', 'Zebra'], $names);
    }
}
