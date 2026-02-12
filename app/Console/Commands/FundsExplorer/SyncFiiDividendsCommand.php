<?php

namespace App\Console\Commands\FundsExplorer;

use App\Models\CompanyTicker;
use App\Services\External\FundsExplorer\FiiDividendSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFiiDividendsCommand extends Command
{
    protected $signature = 'app:sync-fundsexplorer-fii-dividends
        {--ticker= : Codigo de ticker especifico}
        {--limit=50 : Numero maximo de tickers em lote}
        {--only-active : Considera apenas FIIs em carteira}
        {--window-days=30 : Janela minima em dias para nova coleta}
        {--min-delay=60 : Delay minimo (segundos) entre tickers}
        {--max-delay=120 : Delay maximo (segundos) entre tickers}
        {--force : Ignora regras de janela e ultima execucao}';

    protected $description = 'Sincroniza dividendos de FIIs via Funds Explorer (AJAX)';

    public function __construct(private readonly FiiDividendSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $limit = max(1, (int) $this->option('limit'));
            $onlyActive = (bool) $this->option('only-active');
            $windowDays = max(1, (int) $this->option('window-days'));
            $minDelay = max(0, (int) $this->option('min-delay'));
            $maxDelay = max(0, (int) $this->option('max-delay'));
            $force = (bool) $this->option('force');

            $tickerOption = $this->option('ticker');
            $tickers = $tickerOption
                ? $this->resolveSingleTicker((string) $tickerOption)
                : $this->synchronizer->getEligibleTickers($limit, $onlyActive);

            if ($tickers->isEmpty()) {
                $this->info('Nenhum ticker elegivel encontrado para sincronizacao de dividendos.');
                return self::SUCCESS;
            }

            $this->info("Processando {$tickers->count()} ticker(s) de FII via Funds Explorer...");

            $summary = $this->synchronizer->syncTickers(
                tickers: $tickers,
                windowDays: $windowDays,
                minDelaySeconds: $minDelay,
                maxDelaySeconds: $maxDelay,
                force: $force,
                output: fn (string $line) => $this->line($line),
            );

            $this->outputSummary($summary);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Falha na sincronizacao de dividendos FII via Funds Explorer', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error('Erro ao sincronizar dividendos: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveSingleTicker(string $tickerCode): Collection
    {
        $ticker = CompanyTicker::query()
            ->with('company.companyCategory')
            ->where('code', strtoupper(trim($tickerCode)))
            ->first();

        if (! $ticker) {
            throw new \RuntimeException("Ticker {$tickerCode} nao encontrado.");
        }

        return collect([$ticker]);
    }

    private function outputSummary(array $summary): void
    {
        $this->table(
            ['Total', 'Sucesso', 'Sem dados', 'Falhas', 'Ign. hoje', 'Ign. janela'],
            [[
                $summary['total'] ?? 0,
                $summary['success'] ?? 0,
                $summary['no_data'] ?? 0,
                $summary['failed'] ?? 0,
                $summary['skipped_today'] ?? 0,
                $summary['skipped_window'] ?? 0,
            ]]
        );

        collect($summary['details'] ?? [])
            ->groupBy('status')
            ->each(function (Collection $items, string $status): void {
                $codes = $items->pluck('ticker')->implode(', ');
                $message = strtoupper($status) . ': ' . ($codes ?: '—');

                match ($status) {
                    'failed' => $this->warn($message),
                    default => $this->line($message),
                };
            });
    }
}
