<?php

use Illuminate\Support\Facades\Schedule;

// Comandos agendados só rodam em produção
// Em sandbox, o scheduler roda mas não executa nada
if (app()->environment('production')) {
    // MFinance - cada minuto (auto-regula para horario de pregao via TradingHelper)
    Schedule::command('app:update-mfinance-ticker-prices --only-active --stale-minutes=30 --limit=50')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();

    // Reativacao - cada 45 minutos (0min e 45min de cada hora)
    Schedule::command('app:reactivate-tickers --limit=100 --cooldown=45 --stale-minutes=120')
        ->cron('0,45 * * * *')
        ->withoutOverlapping()
        ->runInBackground();

    // Sync lista de ativos - segunda 04:00
    Schedule::command('app:sync-brapi-stock-list --limit=100 --pages=999')
        ->cron('0 4 * * 1')
        ->withoutOverlapping();
}
