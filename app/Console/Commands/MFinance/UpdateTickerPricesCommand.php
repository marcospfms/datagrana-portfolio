<?php

namespace App\Console\Commands\MFinance;

use App\Helpers\TradingHelper;
use App\Services\External\MFinance\MFinanceTickerPriceUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateTickerPricesCommand extends Command
{
    protected $signature = 'app:update-mfinance-ticker-prices
        {--limit=50 : Numero maximo de tickers a processar}
        {--only-active : Considera apenas tickers com posicao consolidada}
        {--stale-minutes=120 : Intervalo minimo em minutos desde a ultima atualizacao}
        {--force : Forca execucao fora da janela de horario padrao}';

    protected $description = 'Atualiza cotacoes e fechamentos dos tickers utilizando a API m_finance';

    public function __construct(private readonly MFinanceTickerPriceUpdater $priceUpdater)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if (!TradingHelper::isTradingWindow((bool) $this->option('force'))) {
                $this->info('Janela de execucao nao atendida (dias uteis entre 08:00 e 18:00). Use --force para sobrescrever.');

                return self::SUCCESS;
            }

            $limit = (int) $this->option('limit');
            $onlyActive = (bool) $this->option('only-active');
            $staleMinutes = (int) $this->option('stale-minutes');

            $tickers = $this->priceUpdater->getEligibleTickers($limit, $onlyActive, $staleMinutes);

            if ($tickers->isEmpty()) {
                $this->info('Nenhum ticker elegivel encontrado para atualizacao.');

                return self::SUCCESS;
            }

            $this->info("Processando {$tickers->count()} ticker(s) com m_finance...");
            $summary = $this->priceUpdater->updateTickers($tickers);

            $this->outputSummary($summary);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Falha na atualizacao de tickers via m_finance', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error('Erro ao processar atualizacao: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function outputSummary(array $summary): void
    {
        $this->table(
            ['Total', 'Sucesso', 'Desativados', 'Falhas'],
            [[
                $summary['total'],
                $summary['success'],
                $summary['disabled'],
                $summary['failed'],
            ]]
        );

        collect($summary['details'])
            ->groupBy('status')
            ->each(function ($items, $status) {
                $codes = $items->pluck('ticker')->implode(', ');
                $message = strtoupper($status) . ': ' . ($codes ?: '—');

                match ($status) {
                    'failed' => $this->warn($message),
                    'disabled' => $this->warn($message),
                    default => $this->line($message),
                };
            });
    }
}
