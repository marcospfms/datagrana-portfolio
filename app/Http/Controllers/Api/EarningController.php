<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Earning\StoreEarningRequest;
use App\Http\Requests\Earning\UpdateEarningRequest;
use App\Http\Resources\EarningResource;
use App\Models\Consolidated;
use App\Models\Earning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Earning::query()
            ->whereHas('consolidated.account', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['earningType', 'consolidated.companyTicker.company.category', 'consolidated.treasure.treasureCategory', 'consolidated.account.bank']);

        if ($request->filled('consolidated_id')) {
            $query->where('consolidated_id', $request->integer('consolidated_id'));
        }

        if ($request->filled('earning_type_id')) {
            $query->where('earning_type_id', $request->integer('earning_type_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('consolidated.companyTicker', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%");
            });
        }

        $earnings = $query->get();

        $sorted = $earnings->sort(function (Earning $a, Earning $b) {
            $dateA = $a->date?->toDateString() ?? '';
            $dateB = $b->date?->toDateString() ?? '';

            if ($dateA !== $dateB) {
                return $dateA < $dateB ? 1 : -1;
            }

            $codeA = $a->consolidated?->companyTicker?->code
                ?? $a->consolidated?->treasure?->code
                ?? '';
            $codeB = $b->consolidated?->companyTicker?->code
                ?? $b->consolidated?->treasure?->code
                ?? '';

            return strcmp($codeA, $codeB);
        })->values();

        $grouped = $sorted
            ->groupBy(fn (Earning $earning) => $earning->date?->toDateString() ?? '0000-00-00')
            ->map(fn ($items, $date) => [
                'date' => $date,
                'data' => EarningResource::collection($items)->values(),
            ])
            ->values();

        return $this->sendResponse($grouped);
    }

    public function store(StoreEarningRequest $request): JsonResponse
    {
        $consolidated = $this->resolveUserConsolidated($request, $request->integer('consolidated_id'));

        if (! $consolidated) {
            return $this->sendError('Posicao consolidada nao encontrada.', [], 404);
        }

        $payload = $this->normalizeValues($request->validated());
        $payload['consolidated_id'] = $consolidated->id;
        $payload['imported_with'] = $payload['imported_with'] ?? 'Manual';

        $earning = Earning::create($payload);

        return $this->sendResponse(
            new EarningResource($earning->load(['earningType', 'consolidated.companyTicker.company.category', 'consolidated.account.bank'])),
            'Provento criado com sucesso.'
        );
    }

    public function show(Request $request, int $earning): JsonResponse
    {
        $earning = $this->findUserEarning($request, $earning);

        if (! $earning) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        return $this->sendResponse(
            new EarningResource($earning->load(['earningType', 'consolidated.companyTicker.company.category', 'consolidated.account.bank']))
        );
    }

    public function update(UpdateEarningRequest $request, int $earning): JsonResponse
    {
        $earningModel = $this->findUserEarning($request, $earning);

        if (! $earningModel) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        $consolidated = $this->resolveUserConsolidated($request, $request->integer('consolidated_id'));

        if (! $consolidated) {
            return $this->sendError('Posicao consolidada nao encontrada.', [], 404);
        }

        $payload = $this->normalizeValues($request->validated());
        $payload['consolidated_id'] = $consolidated->id;
        $payload['imported_with'] = $payload['imported_with'] ?? $earningModel->imported_with ?? 'Manual';

        $earningModel->update($payload);

        return $this->sendResponse(
            new EarningResource($earningModel->fresh()->load(['earningType', 'consolidated.companyTicker.company.category', 'consolidated.account.bank'])),
            'Provento atualizado com sucesso.'
        );
    }

    public function destroy(Request $request, int $earning): JsonResponse
    {
        $earningModel = $this->findUserEarning($request, $earning);

        if (! $earningModel) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        $earningModel->delete();

        return $this->sendResponse([], 'Provento removido com sucesso.');
    }

    public function summary(Request $request): JsonResponse
    {
        $query = Earning::query()
            ->whereHas('consolidated.account', fn ($q) => $q->where('user_id', $request->user()->id));

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        $summary = $query
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(net_value), 0) as total_net')
            ->selectRaw('COALESCE(SUM(gross_value), 0) as total_gross')
            ->selectRaw('COALESCE(SUM(tax), 0) as total_tax')
            ->first();

        return $this->sendResponse([
            'count' => (int) ($summary->count ?? 0),
            'total_net' => number_format((float) ($summary->total_net ?? 0), 8, '.', ''),
            'total_gross' => number_format((float) ($summary->total_gross ?? 0), 8, '.', ''),
            'total_tax' => number_format((float) ($summary->total_tax ?? 0), 8, '.', ''),
        ]);
    }

    public function grouped(Request $request): JsonResponse
    {
        $group = $request->input('group', 'month');

        if (! in_array($group, ['month', 'year'], true)) {
            return $this->sendError('Agrupamento invalido.', [], 422);
        }

        $query = Earning::query()
            ->whereHas('consolidated.account', fn ($q) => $q->where('user_id', $request->user()->id));

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        $format = $group === 'year' ? '%Y' : '%Y-%m';
        $driver = $query->getConnection()->getDriverName();

        $rows = $query
            ->selectRaw(
                $driver === 'sqlite'
                    ? "strftime('{$format}', date) as period"
                    : "DATE_FORMAT(date, '{$format}') as period"
            )
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(net_value), 0) as total_net')
            ->selectRaw('COALESCE(SUM(gross_value), 0) as total_gross')
            ->selectRaw('COALESCE(SUM(tax), 0) as total_tax')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $payload = $rows->map(fn ($row) => [
            'period' => $row->period,
            'count' => (int) $row->count,
            'total_net' => number_format((float) $row->total_net, 8, '.', ''),
            'total_gross' => number_format((float) $row->total_gross, 8, '.', ''),
            'total_tax' => number_format((float) $row->total_tax, 8, '.', ''),
        ]);

        return $this->sendResponse($payload);
    }

    private function resolveUserConsolidated(Request $request, int $id): ?Consolidated
    {
        return Consolidated::query()
            ->where('id', $id)
            ->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();
    }

    private function findUserEarning(Request $request, int $earningId): ?Earning
    {
        return Earning::query()
            ->where('id', $earningId)
            ->whereHas('consolidated.account', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();
    }

    private function normalizeValues(array $payload): array
    {
        $net = isset($payload['net_value']) ? (float) $payload['net_value'] : null;
        $gross = array_key_exists('gross_value', $payload) ? $payload['gross_value'] : null;
        $tax = array_key_exists('tax', $payload) ? $payload['tax'] : null;

        if ($gross === null && $tax !== null && $net !== null) {
            $payload['gross_value'] = $net + (float) $tax;
        }

        if ($tax === null && $gross !== null && $net !== null) {
            $payload['tax'] = max((float) $gross - $net, 0);
        }

        return $payload;
    }
}
