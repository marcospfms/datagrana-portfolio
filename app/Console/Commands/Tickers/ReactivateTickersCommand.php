<?php

namespace App\Console\Commands\Tickers;

use App\Models\CompanyTicker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReactivateTickersCommand extends Command
{
    protected $signature = 'app:reactivate-tickers
        {--limit=50 : Numero maximo de tickers reativados por execucao}
        {--cooldown=45 : Tempo minimo (min) desde a ultima tentativa antes da reativacao}
        {--stale-minutes=120 : Intervalo usado na fila principal para considerar tickers vencidos}';

    protected $description = 'Reativa tickers desativados apos cooldown, recolocando-os no final da fila de atualizacao';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $cooldown = max((int) $this->option('cooldown'), 1);
        $staleMinutes = max((int) $this->option('stale-minutes'), 1);
        $threshold = Carbon::now()->subMinutes($cooldown);

        $tickers = CompanyTicker::query()
            ->where('can_update', 0)
            ->where('status', 1)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('updated_at')
                    ->orWhere('updated_at', '<=', $threshold);
            })
            ->orderByRaw('COALESCE(last_price_updated, updated_at, created_at)')
            ->limit($limit)
            ->get();

        if ($tickers->isEmpty()) {
            $this->info('Nenhum ticker elegivel para reativacao.');
            return self::SUCCESS;
        }

        $reactivated = 0;
        $adjustment = max($staleMinutes - $cooldown, 0);
        $baseline = Carbon::now()->subMinutes($adjustment);

        foreach ($tickers as $ticker) {
            $ticker->can_update = 1;
            $ticker->last_price_updated = $baseline;
            $ticker->save();
            $reactivated++;
        }

        $codes = $tickers->pluck('code')->implode(', ');

        Log::info('Tickers reativados apos cooldown', [
            'count' => $reactivated,
            'cooldown' => $cooldown,
            'stale_minutes' => $staleMinutes,
            'tickers' => $codes,
        ]);

        $this->info("Tickers reativados: {$reactivated}");

        return self::SUCCESS;
    }
}
