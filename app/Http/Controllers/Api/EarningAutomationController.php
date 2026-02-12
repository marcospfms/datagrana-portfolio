<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Automation\DivergenceAutomationRequest;
use App\Http\Requests\Automation\RegisterAutomationRequest;
use App\Http\Resources\Automation\AutomationEarningResource;
use App\Models\CompanyEarning;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Services\Automation\EarningAutomationService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EarningAutomationController extends BaseController
{
    public function __construct(
        private readonly EarningAutomationService $automationService,
        private readonly SubscriptionLimitService $limitService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->get('per_page', 10));
        $currentPage = (int) $request->get('page', 1);

        $paginated = $this->automationService
            ->paginateHydratedPendingPayments($request->user(), $perPage, $currentPage);

        $summary = $this->automationService->summarizePayments($paginated->getCollection());

        $paginated->setCollection(collect($summary['pagamentos']));

        return $this->sendResponse([
            'data' => AutomationEarningResource::collection($paginated->items()),
            'summary' => [
                'count_consolidate' => $summary['countConsolidate'],
                'count_not_registered' => $summary['countNotRegistered'],
                'count_divergences' => $summary['countDivergences'],
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function consolidate(Request $request, CompanyEarning $companyEarning): JsonResponse
    {
        $this->limitService->ensureCanUseAutomations($request->user());

        if (! $this->canAccessCompanyEarning($request->user(), $companyEarning)) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        try {
            DB::beginTransaction();

            $companyEarning->load('earningType');

            $dividend = $this->automationService->findExactDividendFor($request->user(), $companyEarning);

            if (! $dividend) {
                return $this->sendError('Nenhum lancamento encontrado para consolidacao automatica.', [], 422);
            }

            $calculatedValues = $companyEarning->calculateValues((float) $dividend->quantity);

            $dividend->update([
                'company_earning_id' => $companyEarning->id,
                'gross_value' => $calculatedValues['gross_value'],
                'tax' => $calculatedValues['tax'],
            ]);

            DB::commit();

            return $this->sendResponse([], 'Provento consolidado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Erro ao consolidar provento.', ['error' => $e->getMessage()], 422);
        }
    }

    public function register(RegisterAutomationRequest $request, CompanyEarning $companyEarning): JsonResponse
    {
        $this->limitService->ensureCanUseAutomations($request->user());

        if (! $this->canAccessCompanyEarning($request->user(), $companyEarning)) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        try {
            DB::beginTransaction();

            $companyEarning->load('earningType');

            $user = $request->user();
            $accountId = (int) $request->input('account_id');

            if (! $user->accounts()->whereKey($accountId)->exists()) {
                return $this->sendError('Conta nao autorizada.', [], 403);
            }

            $consolidated = Consolidated::firstOrCreate([
                'account_id' => $accountId,
                'company_ticker_id' => $companyEarning->company_ticker_id,
            ], [
                'quantity_current' => 0,
                'average_purchase_price' => 0,
                'total_purchased' => 0,
            ]);

            $quantityUntilApproved = $this->automationService
                ->getQuantityForTicker($user, $companyEarning->company_ticker_id);

            $calculatedValues = $companyEarning->calculateValues($quantityUntilApproved);

            Earning::create([
                'consolidated_id' => $consolidated->id,
                'earning_type_id' => $companyEarning->earning_type_id,
                'company_earning_id' => $companyEarning->id,
                'date' => $companyEarning->payment_date,
                'quantity' => $quantityUntilApproved,
                'net_value' => $calculatedValues['net_value'],
                'gross_value' => $calculatedValues['gross_value'],
                'tax' => $calculatedValues['tax'],
                'imported_with' => 'Sync',
            ]);

            DB::commit();

            return $this->sendResponse([], 'Provento registrado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Erro ao registrar provento.', ['error' => $e->getMessage()], 422);
        }
    }

    public function divergence(
        DivergenceAutomationRequest $request,
        CompanyEarning $companyEarning
    ): JsonResponse {
        $this->limitService->ensureCanUseAutomations($request->user());

        if (! $this->canAccessCompanyEarning($request->user(), $companyEarning)) {
            return $this->sendError('Provento nao encontrado.', [], 404);
        }

        try {
            DB::beginTransaction();

            $companyEarning->load('earningType');

            $userAccountIds = $request->user()->accounts()->pluck('id')->all();

            $earning = Earning::whereHas('consolidated', function ($query) use ($userAccountIds) {
                $query->whereIn('account_id', $userAccountIds);
            })->findOrFail((int) $request->input('earning_id'));

            if ($request->boolean('manter_valores_originais')) {
                $earning->update([
                    'company_earning_id' => $companyEarning->id,
                ]);
            } else {
                $quantityUntilApproved = $this->automationService
                    ->getQuantityForTicker($request->user(), $companyEarning->company_ticker_id);

                $quantity = $quantityUntilApproved > 0 ? $quantityUntilApproved : (float) $earning->quantity;
                $calculatedValues = $companyEarning->calculateValues($quantity);

                $earning->update([
                    'earning_type_id' => $companyEarning->earning_type_id,
                    'company_earning_id' => $companyEarning->id,
                    'quantity' => $quantity,
                    'net_value' => $calculatedValues['net_value'],
                    'gross_value' => $calculatedValues['gross_value'],
                    'tax' => $calculatedValues['tax'],
                ]);
            }

            DB::commit();

            return $this->sendResponse([], 'Divergencia corrigida com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Erro ao corrigir divergencia.', ['error' => $e->getMessage()], 422);
        }
    }

    public function consolidateBatch(Request $request): JsonResponse
    {
        $this->limitService->ensureCanUseAutomations($request->user());

        try {
            DB::beginTransaction();

            $pagamentos = $this->automationService->getHydratedPendingPayments($request->user(), false);

            $consolidated = 0;

            foreach ($pagamentos as $pagamento) {
                if (
                    $pagamento->quantity_current_until_approved_date > 0
                    && $pagamento->dividend_registered
                    && is_null($pagamento->dividend_registered->company_earning_id)
                ) {
                    $pagamento->load('earningType');

                    $dividend = $pagamento->dividend_registered;
                    $calculatedValues = $pagamento->calculateValues((float) $dividend->quantity);

                    $dividend->update([
                        'earning_type_id' => $pagamento->earning_type_id,
                        'company_earning_id' => $pagamento->id,
                        'gross_value' => $calculatedValues['gross_value'],
                        'tax' => $calculatedValues['tax'],
                    ]);
                    $consolidated++;
                }
            }

            DB::commit();

            return $this->sendResponse([], "{$consolidated} proventos consolidados com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Erro ao consolidar proventos.', ['error' => $e->getMessage()], 422);
        }
    }

    public function registerBatch(RegisterAutomationRequest $request): JsonResponse
    {
        $this->limitService->ensureCanUseAutomations($request->user());

        try {
            DB::beginTransaction();

            $user = $request->user();
            $accountId = (int) $request->input('account_id');

            if (! $user->accounts()->whereKey($accountId)->exists()) {
                return $this->sendError('Conta nao autorizada.', [], 403);
            }

            $pagamentos = $this->automationService->getHydratedPendingPayments($user, true);

            $registered = 0;

            foreach ($pagamentos as $pagamento) {
                $possibleDividends = $pagamento->possible_dividend_registered;
                $hasNoPossibleDividends = $possibleDividends instanceof Collection
                    ? $possibleDividends->isEmpty()
                    : empty($possibleDividends);

                if (
                    $pagamento->quantity_current_until_approved_date > 0
                    && is_null($pagamento->dividend_registered)
                    && $hasNoPossibleDividends
                ) {
                    $consolidated = Consolidated::firstOrCreate([
                        'account_id' => $accountId,
                        'company_ticker_id' => $pagamento->company_ticker_id,
                    ], [
                        'quantity_current' => 0,
                        'average_purchase_price' => 0,
                        'total_purchased' => 0,
                    ]);

                    $pagamento->load('earningType');

                    $calculatedValues = $pagamento->calculateValues(
                        (float) $pagamento->quantity_current_until_approved_date
                    );

                    Earning::create([
                        'consolidated_id' => $consolidated->id,
                        'earning_type_id' => $pagamento->earning_type_id,
                        'company_earning_id' => $pagamento->id,
                        'date' => $pagamento->payment_date,
                        'quantity' => $pagamento->quantity_current_until_approved_date,
                        'net_value' => $calculatedValues['net_value'],
                        'gross_value' => $calculatedValues['gross_value'],
                        'tax' => $calculatedValues['tax'],
                        'imported_with' => 'Sync',
                    ]);
                    $registered++;
                }
            }

            DB::commit();

            return $this->sendResponse([], "{$registered} proventos inseridos com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Erro ao inserir proventos.', ['error' => $e->getMessage()], 422);
        }
    }

    private function canAccessCompanyEarning($user, CompanyEarning $companyEarning): bool
    {
        return $this->automationService
            ->getPendingCompanyEarnings($user, [$companyEarning->id])
            ->isNotEmpty();
    }
}
