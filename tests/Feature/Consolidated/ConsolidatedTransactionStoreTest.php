<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedTransactionStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_company_transactions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson('/api/consolidated/transactions', [
            'account_id' => $account->id,
            'transactions' => [
                [
                    'type' => 'company',
                    'date' => now()->toDateTimeString(),
                    'operation' => 'buy',
                    'company_ticker_id' => $ticker->id,
                    'quantity' => 10,
                    'price' => 15.50,
                ],
                [
                    'type' => 'company',
                    'date' => now()->addMinute()->toDateTimeString(),
                    'operation' => 'sell',
                    'company_ticker_id' => $ticker->id,
                    'quantity' => 4,
                    'price' => 20.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('company_transactions', [
            'operation' => 'C',
            'quantity' => 10.00000000,
            'price' => 15.50000000,
            'total_value' => 155.00000000,
        ]);
        $this->assertDatabaseHas('company_transactions', [
            'operation' => 'V',
            'quantity' => 4.00000000,
            'price' => 20.00000000,
            'total_value' => 80.00000000,
        ]);

        $consolidated = Consolidated::where('account_id', $account->id)
            ->where('company_ticker_id', $ticker->id)
            ->first();

        $this->assertNotNull($consolidated);
        $this->assertEquals('6.00000000', (string) $consolidated->quantity_current);
        $this->assertEquals('10.00000000', (string) $consolidated->quantity_purchased);
        $this->assertEquals('4.00000000', (string) $consolidated->quantity_sold);
        $this->assertEquals('155.00000000', (string) $consolidated->total_purchased);
        $this->assertEquals('80.00000000', (string) $consolidated->total_sold);
        $this->assertEquals('15.50000000', (string) $consolidated->average_purchase_price);
        $this->assertEquals('20.00000000', (string) $consolidated->average_selling_price);
        $this->assertFalse($consolidated->closed);
    }

    public function test_returns_error_when_selling_more_than_available(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $ticker = CompanyTicker::factory()->create();

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'quantity_current' => 1,
            'quantity_purchased' => 1,
            'total_purchased' => 10,
        ]);

        $response = $this->postJson('/api/consolidated/transactions', [
            'account_id' => $account->id,
            'transactions' => [
                [
                    'type' => 'company',
                    'date' => now()->toDateTimeString(),
                    'operation' => 'sell',
                    'company_ticker_id' => $ticker->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['errors' => ['insufficient_asset_errors']]);
    }
}
