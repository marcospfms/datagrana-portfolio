<?php

namespace App\Services\External\FundsExplorer;

use App\Models\CompanyEarning;
use App\Models\CompanyTicker;
use App\Models\EarningType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FiiDividendSynchronizer
{
    private const STATUS_INVEST_PAGE = 'https://statusinvest.com.br/fundos-imobiliarios/%s';
    private const FUNDS_EXPLORER_PAGE = 'https://www.fundsexplorer.com.br/rendimentos-e-amortizacoes';
    private const FUNDS_EXPLORER_AJAX = 'https://www.fundsexplorer.com.br/wp-admin/admin-ajax.php';
    private const CLUBE_FII_AJX = 'https://www.clubefii.com.br/proventos-rendimento-distribuicoes-amortizacoes_ajx';
    private const DEFAULT_LIST_ACTION = 'funds-get-Last-dividends';
    private const DEFAULT_PERIOD_ACTION = 'funds-get-last-dividends-by-period';
    private const APPROVED_DATE_TOLERANCE_DAYS = 1;
    private const PAYMENT_DATE_TOLERANCE_DAYS = 3;
    private const VALUE_EPSILON = 0.000001;

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
                'source' => $result['source'] ?? null,
            ];

            if ($output) {
                match ($result['status']) {
                    'success' => $output("✅ {$ticker->code}: {$result['source_label']} salvou {$result['saved']} (duplicados {$result['ignored']})"),
                    'no_data' => $output("📭 {$ticker->code}: sem dados de dividendos nas fontes"),
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
                'source' => null,
                'source_label' => 'Nenhuma fonte',
            ];
        }

        $fetchResult = $this->fetchDividends($ticker);

        if (! $fetchResult['success']) {
            return [
                'status' => 'failed',
                'saved' => 0,
                'ignored' => 0,
                'reason' => $fetchResult['error'] ?? 'Falha ao obter dados das fontes',
                'mark_updated' => false,
                'source' => null,
                'source_label' => 'Nenhuma fonte',
            ];
        }

        $normalized = $fetchResult['rows'];

        if (empty($normalized)) {
            return [
                'status' => 'no_data',
                'saved' => 0,
                'ignored' => 0,
                'reason' => 'Fontes retornaram sem dados para o ticker',
                'mark_updated' => true,
                'source' => null,
                'source_label' => 'Nenhuma fonte',
            ];
        }

        $saved = 0;
        $ignored = 0;

        foreach ($normalized as $dividend) {
            $persisted = $this->saveDividend(
                ticker: $ticker,
                earningType: $earningType,
                data: $dividend,
                origin: $fetchResult['source'],
            );

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
            'source' => $fetchResult['source'],
            'source_label' => $fetchResult['source_label'],
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
            ->where('status', true)
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

    private function fetchDividends(CompanyTicker $ticker): array
    {
        $sources = [
            [
                'name' => 'crawler_statusinvest',
                'label' => 'Status Invest',
                'handler' => fn () => $this->fetchDividendsFromStatusInvest($ticker->code),
            ],
            [
                'name' => 'crawler_fundsexplorer',
                'label' => 'Funds Explorer',
                'handler' => fn () => $this->fetchDividendsFromFundsExplorer($ticker->code),
            ],
            [
                'name' => 'crawler_clubefii',
                'label' => 'Clube FII',
                'handler' => fn () => $this->fetchDividendsFromClubeFii($ticker->code),
            ],
        ];

        $bestValidResult = null;
        $errors = [];

        foreach ($sources as $source) {
            try {
                $result = $source['handler']();
            } catch (\Throwable $exception) {
                Log::warning('FII dividends: erro ao consultar fonte', [
                    'ticker' => $ticker->code,
                    'source' => $source['name'],
                    'error' => $exception->getMessage(),
                ]);

                $errors[] = "{$source['label']}: {$exception->getMessage()}";
                continue;
            }

            if (! ($result['success'] ?? false)) {
                if (! empty($result['error'])) {
                    $errors[] = "{$source['label']}: {$result['error']}";
                }

                continue;
            }

            $rows = $result['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            $currentResult = [
                'success' => true,
                'rows' => $rows,
                'source' => $source['name'],
                'source_label' => $source['label'],
                'error' => null,
            ];

            if ($bestValidResult === null) {
                $bestValidResult = $currentResult;
            }

            if (! $this->shouldFallbackToNextSource($ticker, $rows)) {
                return $currentResult;
            }

            Log::info('FII dividends: fallback por lacuna recente', [
                'ticker' => $ticker->code,
                'source' => $source['name'],
            ]);
        }

        if ($bestValidResult !== null) {
            return $bestValidResult;
        }

        return [
            'success' => false,
            'rows' => [],
            'source' => null,
            'source_label' => 'Nenhuma fonte',
            'error' => empty($errors)
                ? 'Nenhuma fonte retornou dados para o ticker'
                : implode(' | ', $errors),
        ];
    }

    private function fetchDividendsFromStatusInvest(string $ticker): array
    {
        $url = sprintf(self::STATUS_INVEST_PAGE, strtolower(trim($ticker)));
        $response = $this->httpGetWithRetry($url);

        if (! $response->successful()) {
            return [
                'success' => false,
                'rows' => [],
                'error' => "Falha ao carregar pagina do Status Invest ({$response->status()})",
            ];
        }

        return [
            'success' => true,
            'rows' => $this->parseStatusInvestRows($response->body()),
            'error' => null,
        ];
    }

    private function fetchDividendsFromFundsExplorer(string $ticker): array
    {
        $pageResponse = $this->httpGetWithRetry(self::FUNDS_EXPLORER_PAGE . '?ticker=' . urlencode($ticker));

        if (! $pageResponse->successful()) {
            return [
                'success' => false,
                'rows' => [],
                'error' => "Falha ao carregar pagina do Funds Explorer ({$pageResponse->status()})",
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
                'ticker' => strtoupper(trim($ticker)),
            ],
            $nonce
        );

        if ($periodAttempt['success'] && ! empty($periodAttempt['rows'])) {
            return [
                'success' => true,
                'rows' => $this->normalizeDividends($periodAttempt['rows'], $ticker),
                'error' => null,
            ];
        }

        $listAttempt = $this->requestFundsExplorerRows($tableAction, [], $nonce);
        if ($listAttempt['success']) {
            return [
                'success' => true,
                'rows' => $this->normalizeDividends($listAttempt['rows'], $ticker),
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'rows' => [],
            'error' => $periodAttempt['error'] ?? $listAttempt['error'] ?? 'Falha ao consumir endpoint AJAX do Funds Explorer',
        ];
    }

    private function fetchDividendsFromClubeFii(string $ticker): array
    {
        $response = $this->httpGetWithRetry(self::CLUBE_FII_AJX);

        if (! $response->successful()) {
            return [
                'success' => false,
                'rows' => [],
                'error' => "Falha ao carregar pagina do Clube FII ({$response->status()})",
            ];
        }

        return [
            'success' => true,
            'rows' => $this->parseClubeFiiRows($response->body(), $ticker),
            'error' => null,
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

    private function parseStatusInvestRows(string $html): array
    {
        $normalized = [];

        foreach ($this->extractTableRows($html) as $rowText) {
            if (preg_match(
                '/^(Rendimento|Amortização|Amortizacao|Dividendo|JCP|Rend\. Tributado)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+([\d.,]+)$/u',
                $rowText,
                $matches
            ) === 1) {
                $normalized[] = [
                    'type' => $matches[1],
                    'com_date' => $this->parseDateFlexible($matches[2]),
                    'payment_date' => $this->parseDateFlexible($matches[3]),
                    'value_per_quota' => $this->parseValue($matches[4]),
                ];
            }
        }

        if (! empty($normalized)) {
            return array_values(array_filter($normalized, fn (array $row) => $this->isValidNormalizedRow($row)));
        }

        $section = $this->extractBetween(
            $this->extractTextContent($html),
            'Tipo DATA COM Pagamento Valor',
            'Não há histórico de proventos'
        );

        if ($section === null) {
            return [];
        }

        preg_match_all(
            '/(Rendimento|Amortização|Amortizacao|Dividendo|JCP|Rend\. Tributado)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+([\d.,]+)/u',
            $section,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $normalized[] = [
                'type' => $match[1],
                'com_date' => $this->parseDateFlexible($match[2]),
                'payment_date' => $this->parseDateFlexible($match[3]),
                'value_per_quota' => $this->parseValue($match[4]),
            ];
        }

        return array_values(array_filter($normalized, fn (array $row) => $this->isValidNormalizedRow($row)));
    }

    private function parseClubeFiiRows(string $html, string $ticker): array
    {
        $normalized = [];
        $targetTicker = strtoupper(trim($ticker));

        foreach ($this->extractTableRows($html) as $rowText) {
            if (! str_starts_with(strtoupper($rowText), $targetTicker . ' ')) {
                continue;
            }

            if (preg_match(
                '/^' . preg_quote($targetTicker, '/') . '\s+.*?(?:\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2}|N\/D)\s+([\d.,]+)\s+.*?\b(RENDIMENTO|AMORTIZAÇÃO|AMORTIZACAO)\b\s+(\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})/u',
                $rowText,
                $matches
            ) === 1) {
                $normalized[] = [
                    'type' => ucfirst(mb_strtolower($matches[2])),
                    'com_date' => $this->parseDateFlexible($matches[4]),
                    'payment_date' => $this->parseDateFlexible($matches[5]),
                    'value_per_quota' => $this->parseValue($matches[1]),
                ];
            }
        }

        return array_values(array_filter($normalized, fn (array $row) => $this->isValidNormalizedRow($row)));
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

    private function saveDividend(CompanyTicker $ticker, EarningType $earningType, array $data, string $origin): bool
    {
        try {
            $exactExisting = CompanyEarning::query()
                ->where('company_ticker_id', $ticker->id)
                ->where('earning_type_id', $earningType->id)
                ->whereDate('approved_date', $data['com_date'])
                ->whereDate('payment_date', $data['payment_date'])
                ->first();

            if ($exactExisting) {
                if (abs((float) $exactExisting->value - (float) $data['value_per_quota']) > self::VALUE_EPSILON) {
                    $exactExisting->update([
                        'value' => $data['value_per_quota'],
                        'origin' => $origin,
                    ]);

                    return true;
                }

                return false;
            }

            $nearMatch = CompanyEarning::query()
                ->where('company_ticker_id', $ticker->id)
                ->where('earning_type_id', $earningType->id)
                ->where('status', true)
                ->whereBetween('approved_date', [
                    Carbon::parse($data['com_date'])->subDays(self::APPROVED_DATE_TOLERANCE_DAYS)->toDateString(),
                    Carbon::parse($data['com_date'])->addDays(self::APPROVED_DATE_TOLERANCE_DAYS)->toDateString(),
                ])
                ->whereBetween('payment_date', [
                    Carbon::parse($data['payment_date'])->subDays(self::PAYMENT_DATE_TOLERANCE_DAYS)->toDateString(),
                    Carbon::parse($data['payment_date'])->addDays(self::PAYMENT_DATE_TOLERANCE_DAYS)->toDateString(),
                ])
                ->get()
                ->first(fn (CompanyEarning $earning) => abs((float) $earning->value - (float) $data['value_per_quota']) <= self::VALUE_EPSILON);

            if ($nearMatch) {
                return false;
            }

            CompanyEarning::create([
                'company_ticker_id' => $ticker->id,
                'earning_type_id' => $earningType->id,
                'origin' => $origin,
                'status' => true,
                'value' => $data['value_per_quota'],
                'approved_date' => $data['com_date'],
                'payment_date' => $data['payment_date'],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('FII dividends: erro ao salvar dividendo', [
                'ticker' => $ticker->code,
                'origin' => $origin,
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
            ->where('key', 'REND')
            ->orWhere('name', 'LIKE', '%RENDIMENTO%')
            ->orWhere('label', 'RENDIMENTOS')
            ->first();

        if (! $this->earningTypeCache) {
            $this->earningTypeCache = EarningType::query()
                ->where('key', 'REN')
                ->orWhere('label', 'REN')
                ->first();
        }

        return $this->earningTypeCache;
    }

    private function shouldFallbackToNextSource(CompanyTicker $ticker, array $rows): bool
    {
        foreach ($this->recentMonthAnchors() as $monthAnchor) {
            if ($this->rowsCoverRecentMonth($rows, $monthAnchor)) {
                continue;
            }

            if ($this->databaseCoversRecentMonth($ticker, $monthAnchor)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function recentMonthAnchors(): array
    {
        return [
            now()->copy()->startOfMonth(),
            now()->copy()->subMonthNoOverflow()->startOfMonth(),
        ];
    }

    private function rowsCoverRecentMonth(array $rows, CarbonInterface $monthAnchor): bool
    {
        foreach ($rows as $row) {
            $approved = isset($row['com_date']) ? Carbon::parse($row['com_date']) : null;
            $payment = isset($row['payment_date']) ? Carbon::parse($row['payment_date']) : null;

            if (($approved && $approved->isSameMonth($monthAnchor)) || ($payment && $payment->isSameMonth($monthAnchor))) {
                return true;
            }
        }

        return false;
    }

    private function databaseCoversRecentMonth(CompanyTicker $ticker, CarbonInterface $monthAnchor): bool
    {
        return CompanyEarning::query()
            ->where('company_ticker_id', $ticker->id)
            ->where('status', true)
            ->where(function ($query) use ($monthAnchor) {
                $query
                    ->where(function ($approvedQuery) use ($monthAnchor) {
                        $approvedQuery
                            ->whereYear('approved_date', $monthAnchor->year)
                            ->whereMonth('approved_date', $monthAnchor->month);
                    })
                    ->orWhere(function ($paymentQuery) use ($monthAnchor) {
                        $paymentQuery
                            ->whereYear('payment_date', $monthAnchor->year)
                            ->whereMonth('payment_date', $monthAnchor->month);
                    });
            })
            ->exists();
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

    private function extractTableRows(string $html): array
    {
        if (! class_exists(\DOMDocument::class)) {
            return [];
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $rows = [];

        foreach ($document->getElementsByTagName('tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $childNode) {
                if (! in_array($childNode->nodeName, ['td', 'th'], true)) {
                    continue;
                }

                $cellText = $this->normalizeWhitespace($childNode->textContent ?? '');

                if ($cellText !== '') {
                    $cells[] = $cellText;
                }
            }

            $text = ! empty($cells)
                ? implode(' ', $cells)
                : $this->normalizeWhitespace($row->textContent ?? '');

            if ($text !== '') {
                $rows[] = $text;
            }
        }

        return $rows;
    }

    private function extractTextContent(string $html): string
    {
        return $this->normalizeWhitespace(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractBetween(string $content, string $startMarker, string $endMarker): ?string
    {
        $startPosition = mb_strpos($content, $startMarker);

        if ($startPosition === false) {
            return null;
        }

        $startPosition += mb_strlen($startMarker);
        $remaining = mb_substr($content, $startPosition);
        $endPosition = mb_strpos($remaining, $endMarker);

        if ($endPosition === false) {
            return $remaining;
        }

        return mb_substr($remaining, 0, $endPosition);
    }

    private function normalizeWhitespace(string $content): string
    {
        $content = str_replace("\xc2\xa0", ' ', $content);

        return trim((string) preg_replace('/\s+/u', ' ', $content));
    }

    private function isValidNormalizedRow(array $row): bool
    {
        return ! empty($row['com_date'])
            && ! empty($row['payment_date'])
            && isset($row['value_per_quota'])
            && (float) $row['value_per_quota'] > 0;
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
