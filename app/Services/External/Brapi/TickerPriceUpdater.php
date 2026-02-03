<?php

namespace App\Services\External\Brapi;

use App\Helpers\TradingHelper;
use App\Models\CompanyClosing;
use App\Models\CompanyTicker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TickerPriceUpdater
{
    private const DEFAULT_STALE_MINUTES = 120;
    private const FREE_PLAN_CHUNK_SIZE = 1;

    public function __construct(private readonly BrapiService $brapiService)
    {
    }

    public function getEligibleTickers(int $limit, bool $onlyActive, int $staleMinutes = self::DEFAULT_STALE_MINUTES): EloquentCollection
    {
        $today = Carbon::today(config('app.timezone'));
        $isClosingDay = TradingHelper::isLastBusinessDayOfMonth($today);

        $query = CompanyTicker::query()
            ->with('company.companyCategory.coin')
            ->where('status', 1)
            ->where('can_update', 1)
            ->whereHas('company', function ($companyQuery) {
                $companyQuery->where('status', 1)
                    ->whereHas('companyCategory', function ($categoryQuery) {
                        $categoryQuery
                            ->whereIn('reference', ['ACAO', 'FII', 'ETF', 'BDR'])
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

        $tickers->chunk(self::FREE_PLAN_CHUNK_SIZE)->each(function (Collection $chunk) use (&$summary) {
            $codes = $chunk->pluck('code')->implode(',');
            $response = $this->brapiService->getQuote($codes);

            if (!$response->isSuccess()) {
                $chunk->each(function (CompanyTicker $ticker) use (&$summary, $response) {
                    $summary['failed']++;
                    $summary['details'][] = [
                        'ticker' => $ticker->code,
                        'status' => 'failed',
                        'error' => $response->getError(),
                    ];
                });
                Log::warning('Brapi quote request falhou', [
                    'route' => $response->getUrl(),
                    'status' => $response->getStatusCode(),
                    'error' => $response->getError(),
                ]);

                return;
            }

            $results = Arr::get($response->getData(), 'results', []);
            $resultBySymbol = collect($results)->keyBy(fn ($result) => Arr::get($result, 'symbol'));

            $chunk->each(function (CompanyTicker $ticker) use (&$summary, $resultBySymbol) {
                $payload = $resultBySymbol->get($ticker->code);

                if (!$payload) {
                    $summary['failed']++;
                    $summary['details'][] = [
                        'ticker' => $ticker->code,
                        'status' => 'failed',
                        'error' => 'Ticker nao encontrado na resposta do Brapi',
                    ];
                    Log::warning('Ticker ausente na resposta Brapi', ['ticker' => $ticker->code]);

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
                        'error' => 'Preco indisponivel na resposta',
                    ];
                }
            });
        });

        return $summary;
    }

    private function persistTickerData(CompanyTicker $ticker, array $payload): bool
    {
        $price = $this->asDecimal(Arr::get($payload, 'regularMarketPrice'));

        if ($price === null) {
            $ticker->update(['can_update' => 0]);
            Log::warning('Ticker desativado por ausencia de preco na resposta Brapi', [
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

        $longName = trim((string) Arr::get($payload, 'longName', ''));
        $shortName = trim((string) Arr::get($payload, 'shortName', ''));
        $logo = Arr::get($payload, 'logourl');

        $update = [];

        if (empty($company->nickname) && $shortName !== '') {
            $update['nickname'] = preg_replace('/\s+/', ' ', $shortName);
        }

        if (empty($company->name) && $longName !== '') {
            $update['name'] = preg_replace('/\s+/', ' ', $longName);
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
        $price = $this->asDecimal(Arr::get($payload, 'regularMarketPrice'));
        $open = $this->asDecimal(Arr::get($payload, 'regularMarketOpen', $price));
        $high = $this->asDecimal(Arr::get($payload, 'regularMarketDayHigh', $price));
        $low = $this->asDecimal(Arr::get($payload, 'regularMarketDayLow', $price));
        $previousClose = $this->asDecimal(Arr::get($payload, 'regularMarketPreviousClose'));
        $volume = $this->asDecimal(Arr::get($payload, 'regularMarketVolume', 0));

        $marketTime = Arr::get($payload, 'regularMarketTime');
        $dateCarbon = $marketTime
            ? Carbon::createFromTimestamp($marketTime)->timezone(config('app.timezone'))
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

    private function asDecimal(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }
}
