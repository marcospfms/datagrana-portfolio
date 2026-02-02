<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class TradingHelper
{
    /**
     * Mapa de referencia de categoria para segmento MFinance.
     * Legado: Lists::$type_m_finance
     */
    public static array $mFinanceSegments = [
        'ACAO' => 'stocks',
        'FII' => 'fiis',
        'ETF' => 'stocks',
    ];

    /**
     * Verifica se estamos na janela de pregao (seg-sex 08:00-18:59).
     * Legado: UpdateTickerPricesCommand::canExecute()
     */
    public static function isTradingWindow(bool $forced = false): bool
    {
        if ($forced) {
            return true;
        }

        $now = Carbon::now();
        $dayOfWeek = (int) $now->dayOfWeekIso; // 1=seg ... 7=dom
        $hour = (int) $now->format('H');

        return $dayOfWeek >= 1 && $dayOfWeek <= 5 && $hour > 7 && $hour < 19;
    }

    /**
     * Verifica se a data e o ultimo dia util do mes.
     * Proximo dia util esta em mes diferente.
     * Legado: MFinanceTickerPriceUpdater::isLastBusinessDayOfMonth()
     */
    public static function isLastBusinessDayOfMonth(Carbon $date): bool
    {
        $nextBusinessDay = $date->copy()->addDay();
        while ($nextBusinessDay->isWeekend()) {
            $nextBusinessDay->addDay();
        }

        return $nextBusinessDay->month !== $date->month;
    }

    /**
     * Extrai valor decimal com 8 casas de um payload usando lista de chaves.
     * Legado: MFinanceTickerPriceUpdater::extractDecimal()
     */
    public static function extractDecimal(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_numeric($value)) {
                return number_format((float) $value, 8, '.', '');
            }
        }

        return null;
    }
}
