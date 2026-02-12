<?php

namespace App\Services\External\MFinance;

use App\Helpers\TradingHelper;
use App\Models\CompanyClosing;
use App\Models\CompanyTicker;
use App\Services\External\Brapi\TickerPriceUpdater as BrapiTickerPriceUpdater;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MFinanceTickerPriceUpdater
{
    private const DEFAULT_STALE_MINUTES = 120;

    public function __construct(
        private readonly MFinanceService $mFinanceService,
        private readonly BrapiTickerPriceUpdater $brapiTickerPriceUpdater
    ) {
    }

    public function getEligibleTickers(int $limit, bool $onlyActive, int $staleMinutes = self::DEFAULT_STALE_MINUTES): EloquentCollection
    {
        $today = Carbon::today(config('app.timezone'));
        $isClosingDay = TradingHelper::isLastBusinessDayOfMonth($today);

        $query = CompanyTicker::query()
            ->with('company.companyCategory')
            ->where('status', 1)
            ->where('can_update', 1)
            ->whereHas('company', function ($companyQuery) {
                $companyQuery->where('status', 1)
                    ->whereHas('companyCategory', function ($categoryQuery) {
                        $categoryQuery->whereIn('reference', ['ACAO', 'FII', 'ETF', 'BDR'])
                            ->whereHas('coin', function ($coinQuery) {
                                $coinQuery->whereIn('currency_code', ['BRL', 'USD']);
                            });
                    });
            })
            ->orderByRaw('COALESCE(last_price_updated, "1970-01-01 00:00:00") ASC');

        if (!$isClosingDay) {
            $query->where(function ($staleQuery) use ($staleMinutes) {
                $staleQuery->whereNull('last_price_updated')
                    ->orWhere('last_price_updated', '<=', Carbon::now()->subMinutes($staleMinutes));
            });
        }

        if ($onlyActive) {
            if ($isClosingDay) {
                $query->whereHas('consolidated');
            } else {
                $query->whereHas('consolidated', function ($consolidatedQuery) {
                    $consolidatedQuery
                        ->where('quantity_current', '>', 0)
                        ->where(function ($statusQuery) {
                            $statusQuery
                                ->where('closed', false)
                                ->orWhereNull('closed');
                        });
                });
            }
        }

        return $query->limit($limit)->get();
    }

    public function updateTickers(Collection $tickers): array
    {
        $summary = [
            'total' => $tickers->count(),
            'success' => 0,
            'disabled' => 0,
            'failed' => 0,
            'details' => [],
        ];

        if ($tickers->isEmpty()) {
            return $summary;
        }

        $tickers->each(function (CompanyTicker $ticker) use (&$summary) {
            $reference = $ticker->company?->companyCategory?->reference;

            if ($reference === 'BDR') {
                $this->handleFallback($ticker, $summary);
                return;
            }

            $segment = $this->resolveSegment($ticker);

            if (!$segment) {
                Log::info('Segmento m_finance nao identificado; utilizando fallback Brapi', [
                    'ticker' => $ticker->code,
                    'category' => $reference,
                ]);
                $this->handleFallback($ticker, $summary);
                return;
            }

            $response = $this->mFinanceService->getQuote($segment, $ticker->code);

            if (!$response->isSuccess()) {
                if ($response->getStatusCode() === 404) {
                    $this->handleFallback($ticker, $summary);
                    return;
                }

                $summary['failed']++;
                $summary['details'][] = [
                    'ticker' => $ticker->code,
                    'status' => 'failed',
                    'error' => $response->getError(),
                ];
                Log::warning('m_finance quote request falhou', [
                    'ticker' => $ticker->code,
                    'route' => $response->getUrl(),
                    'status' => $response->getStatusCode(),
                    'error' => $response->getError(),
                ]);

                return;
            }

            $payload = $response->getData();

            if (!is_array($payload)) {
                $summary['failed']++;
                $summary['details'][] = [
                    'ticker' => $ticker->code,
                    'status' => 'failed',
                    'error' => 'Resposta inesperada da API m_finance',
                ];
                Log::warning('m_finance retornou payload invalido', [
                    'ticker' => $ticker->code,
                    'route' => $response->getUrl(),
                ]);

                return;
            }

            $success = $this->persistTickerData($ticker, $payload);

            if ($success) {
                $summary['success']++;
                $summary['details'][] = [
                    'ticker' => $ticker->code,
                    'status' => 'success',
                ];
            } else {
                $summary['disabled']++;
                $summary['details'][] = [
                    'ticker' => $ticker->code,
                    'status' => 'disabled',
                    'error' => 'Preco indisponivel ou zerado',
                ];
            }
        });

        return $summary;
    }

    private function handleFallback(CompanyTicker $ticker, array &$summary): void
    {
        $fallbackSummary = $this->brapiTickerPriceUpdater->updateTickers(collect([$ticker]));

        $summary['success'] += $fallbackSummary['success'];
        $summary['disabled'] += $fallbackSummary['disabled'];
        $summary['failed'] += $fallbackSummary['failed'];
        $summary['details'] = array_merge($summary['details'], $fallbackSummary['details']);

        if ($fallbackSummary['success'] === 0) {
            Log::warning('Ticker nao encontrado nas APIs m_finance e brapi', [
                'ticker' => $ticker->code,
                'details' => $fallbackSummary['details'],
            ]);
        }
    }

    private function resolveSegment(CompanyTicker $ticker): ?string
    {
        $reference = $ticker->company?->companyCategory?->reference;

        return $reference
            ? (TradingHelper::$mFinanceSegments[$reference] ?? null)
            : null;
    }

    private function persistTickerData(CompanyTicker $ticker, array $payload): bool
    {
        $price = TradingHelper::extractDecimal($payload, ['lastPrice', 'price', 'close']);

        if ($price === null || (float) $price === 0.0) {
            $ticker->update(['can_update' => 0]);
            Log::warning('Ticker desativado por ausencia de preco na resposta m_finance', [
                'ticker' => $ticker->code,
            ]);

            return false;
        }

        $ticker->fill([
            'last_price' => $price,
            'last_price_updated' => Carbon::now(),
        ]);
        $ticker->save();

        $this->updateCompanyMeta($ticker, $payload);
        $this->syncClosing($ticker, $payload);

        return true;
    }

    private function updateCompanyMeta(CompanyTicker $ticker, array $payload): void
    {
        $company = $ticker->company;

        if (!$company) {
            return;
        }

        $name = trim((string) Arr::get($payload, 'companyName', ''));
        $shortName = trim((string) Arr::get($payload, 'shortName', ''));
        $logo = Arr::get($payload, 'logo');

        $update = [];

        if (empty($company->nickname) && $shortName !== '') {
            $update['nickname'] = preg_replace('/\s+/', ' ', $shortName);
        }

        if (empty($company->name) && $name !== '') {
            $update['name'] = preg_replace('/\s+/', ' ', $name);
        }

        if ($logo && empty($company->photo)) {
            $update['photo'] = $logo;
        }

        if (!empty($update)) {
            $company->update($update);
        }
    }

    private function syncClosing(CompanyTicker $ticker, array $payload): void
    {
        $price = TradingHelper::extractDecimal($payload, ['lastPrice', 'price', 'close']);
        $open = TradingHelper::extractDecimal($payload, ['priceOpen', 'open']);
        $high = TradingHelper::extractDecimal($payload, ['high', 'dayHigh', 'max']);
        $low = TradingHelper::extractDecimal($payload, ['low', 'dayLow', 'min']);
        $previousClose = TradingHelper::extractDecimal($payload, ['closingPrice', 'previousClose']);
        $volume = TradingHelper::extractDecimal($payload, ['volume']);

        $dateString = Arr::get($payload, 'updatedAt') ?? Arr::get($payload, 'latestTradingDay');
        $dateCarbon = $dateString
            ? Carbon::parse($dateString, config('app.timezone'))->timezone(config('app.timezone'))
            : Carbon::now(config('app.timezone'));

        if (!TradingHelper::isLastBusinessDayOfMonth($dateCarbon)) {
            return;
        }

        CompanyClosing::updateOrCreate(
            [
                'company_ticker_id' => $ticker->id,
                'date' => $dateCarbon->toDateString(),
            ],
            [
                'open' => $open ?? $price,
                'high' => $high ?? $price,
                'low' => $low ?? $price,
                'price' => $price,
                'volume' => $volume ?? number_format(0, 8, '.', ''),
                'previous_close' => $previousClose,
            ]
        );
    }
}
