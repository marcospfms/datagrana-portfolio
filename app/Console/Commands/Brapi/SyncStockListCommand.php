<?php

namespace App\Console\Commands\Brapi;

use App\Services\External\Brapi\StockListSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStockListCommand extends Command
{
    protected $signature = 'app:sync-brapi-stock-list
        {--limit=100 : Quantidade de ativos por pagina}
        {--pages=10 : Numero maximo de paginas a percorrer}';

    protected $description = 'Sincroniza a lista de acoes/fiis/etfs da Brapi com companies e company_tickers locais';

    public function __construct(private readonly StockListSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $logFile = storage_path('logs/sync-brapi-stock-list_' . now()->format('Y-m-d_H-i-s') . '.log');
            $logLines = [];

            $limit = (int) $this->option('limit');
            $pages = (int) $this->option('pages');

            $header = "Sincronizando lista de ativos via Brapi (limit={$limit}, pages={$pages})";
            $this->info($header . '...');
            $logLines[] = '[' . now() . "] {$header}";

            $summary = $this->synchronizer->sync($limit, $pages);

            $this->table(
                ['Processados', 'Criados', 'Atualizados', 'Inativados', 'Empresas criadas', 'Empresas atualizadas'],
                [[
                    $summary['processed'],
                    $summary['created_tickers'],
                    $summary['updated_tickers'],
                    $summary['deactivated_tickers'],
                    $summary['created_companies'],
                    $summary['updated_companies'],
                ]]
            );

            $logLines[] = '[' . now() . '] Resumo: ' . json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            foreach ($this->synchronizer->getLastLogDetails() as $detail) {
                $logLines[] = '[' . now() . "] {$detail}";
            }

            file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Falha na sincronizacao de lista de ativos (Brapi)', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            file_put_contents(
                storage_path('logs/sync-brapi-stock-list_errors.log'),
                '[' . now() . '] ' . $exception->getMessage() . PHP_EOL,
                FILE_APPEND
            );

            $this->error('Erro ao sincronizar lista: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
