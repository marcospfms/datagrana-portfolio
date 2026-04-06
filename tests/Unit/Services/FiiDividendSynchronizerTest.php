<?php

namespace Tests\Unit\Services;

use App\Models\CompanyEarning;
use App\Models\CompanyTicker;
use App\Models\EarningType;
use App\Services\External\FundsExplorer\FiiDividendSynchronizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FiiDividendSynchronizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-06 12:00:00');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_uses_status_invest_without_fallback_when_recent_months_are_covered(): void
    {
        $ticker = $this->createFiiTicker('HGRE11');
        EarningType::factory()->create([
            'key' => 'REND',
            'name' => 'Rendimento',
            'label' => 'RENDIMENTOS',
        ]);

        Http::fake([
            'https://statusinvest.com.br/*' => Http::response($this->statusInvestHtml([
                ['Rendimento', '31/03/2026', '15/04/2026', '0,85000000'],
                ['Rendimento', '27/02/2026', '13/03/2026', '0,85000000'],
            ])),
            '*' => Http::response('', 500),
        ]);

        $summary = app(FiiDividendSynchronizer::class)->syncTickers(
            tickers: collect([$ticker]),
            windowDays: 30,
            minDelaySeconds: 0,
            maxDelaySeconds: 0,
            force: true,
        );

        $this->assertSame(1, $summary['success']);
        $this->assertDatabaseCount('company_earnings', 2);
        $this->assertDatabaseHas('company_earnings', [
            'company_ticker_id' => $ticker->id,
            'origin' => 'crawler_statusinvest',
            'approved_date' => '2026-03-31 00:00:00',
            'payment_date' => '2026-04-15 00:00:00',
        ]);
        Http::assertSentCount(1);
    }

    public function test_it_falls_back_to_funds_explorer_when_status_invest_misses_recent_months(): void
    {
        $ticker = $this->createFiiTicker('HGRE11');
        EarningType::factory()->create([
            'key' => 'REND',
            'name' => 'Rendimento',
            'label' => 'RENDIMENTOS',
        ]);

        Http::fake([
            'https://statusinvest.com.br/*' => Http::response($this->statusInvestHtml([
                ['Rendimento', '30/12/2025', '15/01/2026', '1,50000000'],
            ])),
            'https://www.fundsexplorer.com.br/rendimentos-e-amortizacoes*' => Http::response(
                '<div id="table-rendimentos-container" data-action="funds-get-Last-dividends" data-nonce="abc"></div>'
                . '<div id="lista-período-rendimentos" data-action="funds-get-last-dividends-by-period"></div>'
            ),
            'https://www.fundsexplorer.com.br/wp-admin/admin-ajax.php' => Http::response([
                'data' => [
                    [
                        'post_title' => 'HGRE11',
                        'tipo' => 'Rendimento',
                        'valor' => '0,850',
                        'data_base' => '31/03/2026',
                        'data_pagamento' => '15/04/2026',
                    ],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);

        $summary = app(FiiDividendSynchronizer::class)->syncTickers(
            tickers: collect([$ticker]),
            windowDays: 30,
            minDelaySeconds: 0,
            maxDelaySeconds: 0,
            force: true,
        );

        $this->assertSame(1, $summary['success']);
        $this->assertDatabaseHas('company_earnings', [
            'company_ticker_id' => $ticker->id,
            'origin' => 'crawler_fundsexplorer',
            'approved_date' => '2026-03-31 00:00:00',
            'payment_date' => '2026-04-15 00:00:00',
        ]);
    }

    public function test_it_falls_back_to_clube_fii_when_other_sources_do_not_cover_recent_months(): void
    {
        $ticker = $this->createFiiTicker('HGRE11');
        EarningType::factory()->create([
            'key' => 'REND',
            'name' => 'Rendimento',
            'label' => 'RENDIMENTOS',
        ]);

        Http::fake([
            'https://statusinvest.com.br/*' => Http::response($this->statusInvestHtml([
                ['Rendimento', '30/12/2025', '15/01/2026', '1,50000000'],
            ])),
            'https://www.fundsexplorer.com.br/rendimentos-e-amortizacoes*' => Http::response(
                '<div id="table-rendimentos-container" data-action="funds-get-Last-dividends" data-nonce="abc"></div>'
                . '<div id="lista-período-rendimentos" data-action="funds-get-last-dividends-by-period"></div>'
            ),
            'https://www.fundsexplorer.com.br/wp-admin/admin-ajax.php' => Http::response(['data' => []]),
            'https://www.clubefii.com.br/proventos-rendimento-distribuicoes-amortizacoes_ajx' => Http::response($this->clubeFiiHtml([
                'HGRE11 Pátria Escritórios 122,15 +2,15% 02/04/2026 18:37:00 0,850 0,00% 0,7% 10,27% RENDIMENTO 03/2026 31/03/2026 15/04/2026 BRHGRECTF006 120,50 0,71% 0,85 31/03/2026 17:43:24',
            ])),
            '*' => Http::response('', 500),
        ]);

        $summary = app(FiiDividendSynchronizer::class)->syncTickers(
            tickers: collect([$ticker]),
            windowDays: 30,
            minDelaySeconds: 0,
            maxDelaySeconds: 0,
            force: true,
        );

        $this->assertSame(1, $summary['success']);
        $this->assertDatabaseHas('company_earnings', [
            'company_ticker_id' => $ticker->id,
            'origin' => 'crawler_clubefii',
            'approved_date' => '2026-03-31 00:00:00',
            'payment_date' => '2026-04-15 00:00:00',
        ]);
    }

    public function test_it_avoids_duplicates_when_existing_record_has_nearby_dates(): void
    {
        $ticker = $this->createFiiTicker('HGRE11');
        $earningType = EarningType::factory()->create([
            'key' => 'REND',
            'name' => 'Rendimento',
            'label' => 'RENDIMENTOS',
        ]);

        CompanyEarning::factory()->create([
            'company_ticker_id' => $ticker->id,
            'earning_type_id' => $earningType->id,
            'origin' => 'crawler_investidor10',
            'value' => 1.5,
            'approved_date' => '2025-12-30',
            'payment_date' => '2026-01-15',
        ]);

        Http::fake([
            'https://statusinvest.com.br/*' => Http::response($this->statusInvestHtml([
                ['Rendimento', '31/03/2026', '15/04/2026', '0,85000000'],
                ['Rendimento', '27/02/2026', '13/03/2026', '0,85000000'],
                ['Rendimento', '30/12/2025', '13/01/2026', '1,50000000'],
            ])),
            '*' => Http::response('', 500),
        ]);

        $summary = app(FiiDividendSynchronizer::class)->syncTickers(
            tickers: collect([$ticker]),
            windowDays: 30,
            minDelaySeconds: 0,
            maxDelaySeconds: 0,
            force: true,
        );

        $this->assertSame(1, $summary['success']);
        $this->assertSame(3, CompanyEarning::count());
        $this->assertSame(1, CompanyEarning::query()->where('value', 1.5)->count());
        $this->assertDatabaseHas('company_earnings', [
            'company_ticker_id' => $ticker->id,
            'origin' => 'crawler_investidor10',
            'approved_date' => '2025-12-30 00:00:00',
            'payment_date' => '2026-01-15 00:00:00',
        ]);
    }

    private function createFiiTicker(string $code): CompanyTicker
    {
        return CompanyTicker::factory()
            ->for(\App\Models\Company::factory()->for(\App\Models\CompanyCategory::factory()->fii()))
            ->withCode($code)
            ->create([
                'last_earnings_updated' => null,
            ]);
    }

    private function statusInvestHtml(array $rows): string
    {
        $tableRows = collect($rows)->map(
            fn (array $row) => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $row[0],
                $row[1],
                $row[2],
                $row[3],
            )
        )->implode('');

        return "<html><body><table>{$tableRows}</table></body></html>";
    }

    private function clubeFiiHtml(array $rows): string
    {
        $tableRows = collect($rows)->map(
            fn (string $row) => sprintf('<tr><td>%s</td></tr>', $row)
        )->implode('');

        return "<html><body><table>{$tableRows}</table></body></html>";
    }
}
