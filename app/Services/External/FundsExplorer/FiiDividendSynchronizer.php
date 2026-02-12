<?php

namespace App\Services\External\FundsExplorer;

use App\Models\CompanyEarning;
use App\Models\CompanyTicker;
use App\Models\EarningType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FiiDividendSynchronizer
{
    private const FUNDS_EXPLORER_PAGE = 'https://www.fundsexplorer.com.br/rendimentos-e-amortizacoes';
    private const FUNDS_EXPLORER_AJAX = 'https://www.fundsexplorer.com.br/wp-admin/admin-ajax.php';
    private const DEFAULT_LIST_ACTION = 'funds-get-Last-dividends';
    private const DEFAULT_PERIOD_ACTION = 'funds-get-last-dividends-by-period';

    private ?EarningType $earningTypeCache = null;

    public function getEligibleTickers(int $limit, bool $onlyActive): EloquentCollection
    {
        $query = CompanyTicker::query()
            ->with('company.companyCategory')
            ->where('status', 1)
            ->where('can_update', 1)
            ->whereHas('company', function ($companyQuery) {
                $companyQuery->where('status', 1)
                    ->whereHas('companyCategory', function ($categoryQuery) {
                        $categoryQuery->where('reference', 'FII');
                    });
            })
            ->orderByRaw('COALESCE(last_earnings_updated, "1970-01-01 00:00:00") ASC');

        if ($onlyActive) {
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

        return $query->limit(max(1, $limit))->get();
    }

    public function syncTickers(
        Collection $tickers,
        int $windowDays,
        int $minDelaySeconds,
        int $maxDelaySeconds,
        bool $force = false,
        ?callable $output = null
    ): array {
        $summary = [
            'total' => $tickers->count(),
            'success' => 0,
            'no_data' => 0,
            'failed' => 0,
            'skipped_today' => 0,
            'skipped_window' => 0,
            'details' => [],
        ];

        if ($tickers->isEmpty()) {
            return $summary;
        }

        [$minDelaySeconds, $maxDelaySeconds] = $this->normalizeDelayBounds($minDelaySeconds, $maxDelaySeconds);

        $tickers->values()->each(function (CompanyTicker $ticker, int $index) use (
            &$summary,
            $windowDays,
            $minDelaySeconds,
            $maxDelaySeconds,
            $force,
            $output,
            $tickers
        ) {
            $check = $force
                ? ['should_update' => true, 'reason' => 'Execucao forcada (--force)']
                : $this->shouldUpdateFii($ticker, $windowDays);

            if (! $check['should_update']) {
                $status = str_contains($check['reason'], 'Ja atualizado') ? 'skipped_today' : 'skipped_window';
                $summary[$status]++;
                $summary['details'][] = [
                    'ticker' => $ticker->code,
                    'status' => $status,
                    'reason' => $check['reason'],
                ];

                if ($output) {
                    $prefix = $status === 'skipped_today' ? '⏭️' : '⏳';
                    $output("{$prefix} {$ticker->code}: {$check['reason']}");
                }

                return;
            }

            $result = $this->syncTicker($ticker);
            $summary[$result['status']]++;
            $summary['details'][] = [
                'ticker' => $ticker->code,
                'status' => $result['status'],
                'saved' => $result['saved'],
                'ignored' => $result['ignored'],
                'reason' => $result['reason'],
            ];

            if ($output) {
                match ($result['status']) {
                    'success' => $output("✅ {$ticker->code}: salvos {$result['saved']} (duplicados {$result['ignored']})"),
                    'no_data' => $output("📭 {$ticker->code}: sem dados de dividendos na fonte"),
                    default => $output("❌ {$ticker->code}: {$result['reason']}"),
                };
            }

            if ($result['mark_updated']) {
                $ticker->update(['last_earnings_updated' => now()]);
            }

            $isLast = $index >= ($tickers->count() - 1);
            if (! $isLast && $maxDelaySeconds > 0) {
                $wait = random_int($minDelaySeconds, $maxDelaySeconds);

                if ($output) {
                    $output("⏳ Aguardando {$wait}s para proximo ticker...");
                }

                sleep($wait);
            }
        });

        return $summary;
    }

    private function syncTicker(CompanyTicker $ticker): array
    {
        $earningType = $this->resolveEarningType();

        if (! $earningType) {
            return [
                'status' => 'failed',
                'saved' => 0,
                'ignored' => 0,
                'reason' => 'Tipo de earning REN nao encontrado',
                'mark_updated' => false,
            ];
        }

        $fetchResult = $this->fetchDividendsFromFundsExplorer($ticker->code);

        if (! $fetchResult['success']) {
            return [
                'status' => 'failed',
                'saved' => 0,
                'ignored' => 0,
                'reason' => $fetchResult['error'] ?? 'Falha ao obter dados da fonte',
                'mark_updated' => false,
            ];
        }

        $normalized = $this->normalizeDividends($fetchResult['rows'], $ticker->code);

        if (empty($normalized)) {
            return [
                'status' => 'no_data',
                'saved' => 0,
                'ignored' => 0,
                'reason' => 'Fonte retornou sem dados para o ticker',
                'mark_updated' => true,
            ];
        }

        $saved = 0;
        $ignored = 0;

        foreach ($normalized as $dividend) {
            $persisted = $this->saveDividend($ticker, $earningType, $dividend);

            if ($persisted) {
                $saved++;
            } else {
                $ignored++;
            }
        }

        return [
            'status' => 'success',
            'saved' => $saved,
            'ignored' => $ignored,
            'reason' => 'Processado com sucesso',
            'mark_updated' => true,
        ];
    }

    private function shouldUpdateFii(CompanyTicker $ticker, int $windowDays): array
    {
        if ($ticker->last_earnings_updated && $ticker->last_earnings_updated->isToday()) {
            return [
                'should_update' => false,
                'reason' => 'Ja atualizado hoje as ' . $ticker->last_earnings_updated->format('H:i'),
            ];
        }

        $lastEarning = CompanyEarning::where('company_ticker_id', $ticker->id)
            ->orderBy('approved_date', 'desc')
            ->first();

        if (! $lastEarning) {
            return [
                'should_update' => true,
                'reason' => 'Sem historico de dividendos',
            ];
        }

        $windowDays = max(1, $windowDays);
        $lastApprovedDate = Carbon::parse($lastEarning->approved_date);
        $nextWindow = $lastApprovedDate->copy()->addDays($windowDays);

        if (now()->gte($nextWindow)) {
            return [
                'should_update' => true,
                'reason' => sprintf(
                    'Janela atingida (ultimo: %s, janela: %sd)',
                    $lastApprovedDate->format('d/m/Y'),
                    $windowDays
                ),
            ];
        }

        return [
            'should_update' => false,
            'reason' => sprintf(
                'Aguardando janela (proxima: %s)',
                $nextWindow->format('d/m/Y')
            ),
        ];
    }

    private function fetchDividendsFromFundsExplorer(string $ticker): array
    {
        $pageResponse = $this->httpGetWithRetry(self::FUNDS_EXPLORER_PAGE . '?ticker=' . urlencode($ticker));

        if (! $pageResponse->successful()) {
            return [
                'success' => false,
                'rows' => [],
                'error' => "Falha ao carregar pagina da fonte ({$pageResponse->status()})",
            ];
        }

        $html = $pageResponse->body();
        $nonce = $this->extractDataAttribute($html, 'data-nonce');
        $tableAction = $this->extractDataAttribute($html, 'data-action', 'table-rendimentos-container')
            ?? self::DEFAULT_LIST_ACTION;
        $periodAction = $this->extractDataAttribute($html, 'data-action', 'lista-período-rendimentos')
            ?? self::DEFAULT_PERIOD_ACTION;

        $periodAttempt = $this->requestFundsExplorerRows(
            $periodAction,
            [
                'mes' => -1,
                'ano' => 0,
                'ticker' => strtolower($ticker),
            ],
            $nonce
        );

        if ($periodAttempt['success'] && ! empty($periodAttempt['rows'])) {
            return [
                'success' => true,
                'rows' => $periodAttempt['rows'],
                'error' => null,
            ];
        }

        $listAttempt = $this->requestFundsExplorerRows($tableAction, [], $nonce);
        if ($listAttempt['success']) {
            return [
                'success' => true,
                'rows' => $listAttempt['rows'],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'rows' => [],
            'error' => $periodAttempt['error'] ?? $listAttempt['error'] ?? 'Falha ao consumir endpoint AJAX da fonte',
        ];
    }

    private function requestFundsExplorerRows(string $action, array $payload, ?string $nonce): array
    {
        $requestPayload = array_merge(['action' => $action], $payload);
        $headers = [];

        if (! empty($nonce)) {
            $headers['X-CSRF-TOKEN'] = $nonce;
        }

        $response = $this->httpPostWithRetry(self::FUNDS_EXPLORER_AJAX, $requestPayload, $headers);

        if (! $response->successful()) {
            Log::warning('FundsExplorer: endpoint retornou erro HTTP', [
                'action' => $action,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'rows' => [],
                'error' => "HTTP {$response->status()} em {$action}",
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'success' => false,
                'rows' => [],
                'error' => "Payload invalido em {$action}",
            ];
        }

        $rows = $data['data'] ?? [];

        return [
            'success' => true,
            'rows' => is_array($rows) ? $rows : [],
            'error' => null,
        ];
    }

    private function normalizeDividends(array $rows, string $ticker): array
    {
        $normalized = [];
        $targetTicker = strtoupper(trim($ticker));

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowTicker = strtoupper(trim((string) ($row['post_title'] ?? '')));
            if ($rowTicker !== '' && $rowTicker !== $targetTicker) {
                continue;
            }

            $value = $this->parseValue($row['valor'] ?? null);
            $approvedDate = $this->parseDateFlexible($row['data_base'] ?? null);
            $paymentDate = $this->parseDateFlexible($row['data_pagamento'] ?? null) ?? $approvedDate;

            if ($value <= 0 || ! $approvedDate || ! $paymentDate) {
                continue;
            }

            $normalized[] = [
                'type' => trim((string) ($row['tipo'] ?? 'Rendimento')),
                'com_date' => $approvedDate,
                'payment_date' => $paymentDate,
                'value_per_quota' => $value,
            ];
        }

        return $normalized;
    }

    private function saveDividend(CompanyTicker $ticker, EarningType $earningType, array $data): bool
    {
        try {
            $existing = CompanyEarning::where('company_ticker_id', $ticker->id)
                ->where('earning_type_id', $earningType->id)
                ->where('approved_date', $data['com_date'])
                ->where('payment_date', $data['payment_date'])
                ->first();

            if ($existing) {
                if ((float) $existing->value !== (float) $data['value_per_quota']) {
                    $existing->update([
                        'value' => $data['value_per_quota'],
                        'origin' => 'crawler_fundsexplorer',
                    ]);

                    return true;
                }

                return false;
            }

            CompanyEarning::create([
                'company_ticker_id' => $ticker->id,
                'earning_type_id' => $earningType->id,
                'origin' => 'crawler_fundsexplorer',
                'status' => true,
                'value' => $data['value_per_quota'],
                'approved_date' => $data['com_date'],
                'payment_date' => $data['payment_date'],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('FundsExplorer: erro ao salvar dividendo', [
                'ticker' => $ticker->code,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveEarningType(): ?EarningType
    {
        if ($this->earningTypeCache) {
            return $this->earningTypeCache;
        }

        $this->earningTypeCache = EarningType::query()
            ->where('key', 'REN')
            ->orWhere('name', 'LIKE', '%RENDIMENTO%')
            ->orWhere('label', 'REN')
            ->first();

        return $this->earningTypeCache;
    }

    private function extractDataAttribute(string $html, string $attribute, ?string $contextId = null): ?string
    {
        $pattern = $contextId
            ? '/id="' . preg_quote($contextId, '/') . '".{0,700}?' . preg_quote($attribute, '/') . '="([^"]+)"/si'
            : '/' . preg_quote($attribute, '/') . '="([^"]+)"/si';

        if (preg_match($pattern, $html, $matches) === 1) {
            return $matches[1] ?? null;
        }

        return null;
    }

    private function httpGetWithRetry(string $url, int $retries = 2)
    {
        $attempt = 0;

        start:
        $attempt++;

        try {
            return Http::withHeaders([
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->timeout(25)->get($url);
        } catch (\Throwable $exception) {
            if ($attempt <= $retries) {
                usleep(400000);
                goto start;
            }

            throw $exception;
        }
    }

    private function httpPostWithRetry(string $url, array $payload, array $headers = [], int $retries = 2)
    {
        $attempt = 0;

        start:
        $attempt++;

        try {
            return Http::withHeaders(array_merge([
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'application/json, text/plain, */*',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => self::FUNDS_EXPLORER_PAGE,
                'Origin' => 'https://www.fundsexplorer.com.br',
            ], $headers))->asForm()->timeout(25)->post($url, $payload);
        } catch (\Throwable $exception) {
            if ($attempt <= $retries) {
                usleep(400000);
                goto start;
            }

            throw $exception;
        }
    }

    private function parseValue(mixed $value): float
    {
        if ($value === null) {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';

        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, ',')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }

    private function parseDateFlexible(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $cleaned = trim((string) $date);
        if ($cleaned === '' || $cleaned === '-') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleaned) === 1) {
            return $cleaned;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $cleaned)->format('Y-m-d');
        } catch (\Throwable $exception) {
            try {
                return Carbon::parse($cleaned)->format('Y-m-d');
            } catch (\Throwable $nestedException) {
                return null;
            }
        }
    }

    private function normalizeDelayBounds(int $minDelaySeconds, int $maxDelaySeconds): array
    {
        $minDelaySeconds = max(0, $minDelaySeconds);
        $maxDelaySeconds = max(0, $maxDelaySeconds);

        if ($minDelaySeconds > $maxDelaySeconds) {
            [$minDelaySeconds, $maxDelaySeconds] = [$maxDelaySeconds, $minDelaySeconds];
        }

        return [$minDelaySeconds, $maxDelaySeconds];
    }

    private function getRandomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
        ];

        return $agents[array_rand($agents)];
    }
}
