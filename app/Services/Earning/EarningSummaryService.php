<?php

namespace App\Services\Earning;

use App\Models\Earning;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EarningSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?string $from = null, ?string $to = null): array
    {
        $query = Earning::query()
            ->whereHas('consolidated.account', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'earningType:id,name,short_name,hex_color',
                'consolidated.companyTicker.company:id,name',
                'consolidated.treasure:id,name,code',
                'consolidated.account:id,nickname,account',
            ]);

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        /** @var Collection<int, Earning> $earnings */
        $earnings = $query
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $totalNet = (float) $earnings->sum(fn (Earning $earning) => (float) $earning->net_value);
        $totalGross = (float) $earnings->sum(fn (Earning $earning) => (float) ($earning->gross_value ?? 0));
        $totalTax = (float) $earnings->sum(fn (Earning $earning) => (float) ($earning->tax ?? 0));

        return [
            'count' => $earnings->count(),
            'total_net' => $this->toDecimal($totalNet),
            'total_gross' => $this->toDecimal($totalGross),
            'total_tax' => $this->toDecimal($totalTax),
            'grand_total' => round($totalNet, 2),
            'monthly_data' => $this->buildMonthlyData($earnings),
            'monthly_totals' => $this->buildMonthlyTotals($earnings),
            'category_data' => $this->buildCategoryData($earnings),
            'chart_data' => $this->buildChartData($earnings, 12),
        ];
    }

    /**
     * @param Collection<int, Earning> $earnings
     * @return array<int, array<string, mixed>>
     */
    private function buildMonthlyData(Collection $earnings): array
    {
        $years = $earnings
            ->groupBy(fn (Earning $earning) => (int) ($earning->date?->format('Y') ?? 0))
            ->sortKeys();

        return $years->map(function (Collection $yearItems, int $year): array {
            $yearTotal = 0.0;
            $months = [];

            for ($month = 1; $month <= 12; $month++) {
                $monthItems = $yearItems
                    ->filter(fn (Earning $item) => (int) $item->date?->format('n') === $month)
                    ->values();

                $monthTotal = (float) $monthItems->sum(fn (Earning $item) => (float) $item->net_value);
                $yearTotal += $monthTotal;
                $monthCount = $monthItems->count();

                $months[] = [
                    'month' => $month,
                    'value' => round($monthTotal, 2),
                    'summary' => [
                        'total_value' => round($monthTotal, 2),
                        'total_count' => $monthCount,
                        'average_per_earning' => $monthCount > 0 ? round($monthTotal / $monthCount, 2) : 0,
                        'categories' => $this->buildMonthCategoryData($monthItems),
                    ],
                    'earnings' => $monthItems->map(fn (Earning $earning) => $this->mapMonthEarning($earning))->all(),
                ];
            }

            return [
                'year' => $year,
                'months' => $months,
                'total' => round($yearTotal, 2),
                'average' => round($yearTotal / 12, 2),
            ];
        })->values()->all();
    }

    /**
     * @param Collection<int, Earning> $monthItems
     * @return array<int, array<string, mixed>>
     */
    private function buildMonthCategoryData(Collection $monthItems): array
    {
        return $monthItems
            ->groupBy(fn (Earning $earning) => $earning->earningType?->name ?? 'Sem categoria')
            ->map(function (Collection $group, string $name): array {
                $total = (float) $group->sum(fn (Earning $item) => (float) $item->net_value);
                $count = $group->count();

                return [
                    'name' => $name,
                    'count' => $count,
                    'total' => round($total, 2),
                    'average' => $count > 0 ? round($total / $count, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Earning> $earnings
     * @return array<int, float>
     */
    private function buildMonthlyTotals(Collection $earnings): array
    {
        $totals = [];

        for ($month = 1; $month <= 12; $month++) {
            $total = (float) $earnings
                ->filter(fn (Earning $earning) => (int) $earning->date?->format('n') === $month)
                ->sum(fn (Earning $earning) => (float) $earning->net_value);

            $totals[] = round($total, 2);
        }

        return $totals;
    }

    /**
     * @param Collection<int, Earning> $earnings
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryData(Collection $earnings): array
    {
        return $earnings
            ->groupBy(fn (Earning $earning) => $earning->earningType?->name ?? 'Sem categoria')
            ->map(function (Collection $group, string $name): array {
                $first = $group->first();

                return [
                    'name' => $name,
                    'total' => round((float) $group->sum(fn (Earning $item) => (float) $item->net_value), 2),
                    'count' => $group->count(),
                    'color' => $first?->earningType?->hex_color ?? '#64748b',
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Earning> $earnings
     * @return array<int, array{month: string, value: float}>
     */
    private function buildChartData(Collection $earnings, int $monthsBack = 12): array
    {
        $labels = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Out',
            11 => 'Nov',
            12 => 'Dez',
        ];

        $points = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');
            $value = (float) $earnings
                ->filter(
                    fn (Earning $item) => (int) $item->date?->format('Y') === $year
                        && (int) $item->date?->format('n') === $month
                )
                ->sum(fn (Earning $item) => (float) $item->net_value);

            $points[] = [
                'month' => sprintf('%s/%d', $labels[$month], $year),
                'value' => round($value, 2),
            ];
        }

        return $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMonthEarning(Earning $earning): array
    {
        $type = null;
        $assetName = null;
        $ticker = null;

        if ($earning->consolidated?->companyTicker) {
            $type = 'company';
            $assetName = $earning->consolidated->companyTicker->company?->name;
            $ticker = $earning->consolidated->companyTicker->code;
        } elseif ($earning->consolidated?->treasure) {
            $type = 'treasure';
            $assetName = $earning->consolidated->treasure->name;
            $ticker = $earning->consolidated->treasure->code ?: $earning->consolidated->treasure->name;
        }

        return [
            'id' => $earning->id,
            'date' => $earning->date?->toDateString(),
            'net_value' => round((float) $earning->net_value, 2),
            'quantity' => round((float) $earning->quantity, 8),
            'type' => $type,
            'asset_name' => $assetName,
            'ticker' => $ticker,
            'account_name' => $earning->consolidated?->account?->nickname
                ?? $earning->consolidated?->account?->account,
            'earning_type' => $earning->earningType?->name,
        ];
    }

    private function toDecimal(float $value): string
    {
        return number_format($value, 8, '.', '');
    }
}
