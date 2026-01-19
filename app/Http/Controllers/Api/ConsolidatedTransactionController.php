<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientAssetException;
use App\Http\Requests\Consolidated\StoreTransactionRequest;
use App\Http\Requests\Consolidated\UpdateTransactionRequest;
use App\Http\Resources\CompanyTransactionResource;
use App\Http\Resources\TreasureTransactionResource;
use App\Models\Account;
use App\Models\CompanyTicker;
use App\Models\CompanyTransaction;
use App\Models\Treasure;
use App\Models\TreasureTransaction;
use App\Services\Consolidated\ConsolidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConsolidatedTransactionController extends BaseController
{
    public function store(StoreTransactionRequest $request, ConsolidationService $consolidationService): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::where('id', $validated['account_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $sortedTransactions = collect(array_reverse($validated['transactions']))
            ->sortBy('date')
            ->values()
            ->all();

        $errors = [];
        $createdCompanyTransactions = [];
        $createdTreasureTransactions = [];

        DB::beginTransaction();

        try {
            foreach ($sortedTransactions as $index => $transactionData) {
                try {
                    if ($transactionData['type'] === 'company') {
                        $consolidated = $consolidationService->getOrCreateConsolidated(
                            $account->id,
                            $transactionData['company_ticker_id'],
                            null
                        );

                        $transaction = new CompanyTransaction([
                            'consolidated_id' => $consolidated->id,
                            'date' => $transactionData['date'],
                            'operation' => $transactionData['operation'] === 'buy' ? 'C' : 'V',
                            'quantity' => $transactionData['quantity'],
                            'price' => $transactionData['price'],
                            'total_value' => $transactionData['quantity'] * $transactionData['price'],
                        ]);

                        $companyTicker = CompanyTicker::with('company')
                            ->find($transactionData['company_ticker_id']);

                        $assetInfo = null;
                        if ($companyTicker) {
                            $assetInfo = [
                                'ticker' => [
                                    'code' => $companyTicker->code,
                                    'trade_code' => $companyTicker->trade_code,
                                ],
                                'company' => [
                                    'name' => $companyTicker->company?->name,
                                ],
                            ];
                        }

                        $consolidationService->processCreating($transaction, $consolidated, $index, $assetInfo);
                        $createdCompanyTransactions[] = $transaction;
                    } else {
                        $consolidated = $consolidationService->getOrCreateConsolidated(
                            $account->id,
                            null,
                            $transactionData['treasure_id']
                        );

                        $transaction = new TreasureTransaction([
                            'consolidated_id' => $consolidated->id,
                            'date' => $transactionData['date'],
                            'operation' => $transactionData['operation'] === 'buy' ? 'C' : 'V',
                            'invested_value' => $transactionData['invested_value'],
                            'quantity' => $transactionData['quantity'],
                            'price' => $transactionData['quantity'] > 0
                                ? $transactionData['invested_value'] / $transactionData['quantity']
                                : 0,
                        ]);

                        $treasure = Treasure::find($transactionData['treasure_id']);

                        $assetInfo = null;
                        if ($treasure) {
                            $assetInfo = [
                                'name' => $treasure->name,
                                'code' => $treasure->code,
                            ];
                        }

                        $consolidationService->processCreating($transaction, $consolidated, $index, $assetInfo);
                        $createdTreasureTransactions[] = $transaction;
                    }
                } catch (InsufficientAssetException $exception) {
                    $errors[] = [
                        'transaction_index' => $index,
                        'message' => $exception->getMessage(),
                        'error_data' => $exception->getErrorData(),
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();

                return $this->sendError(
                    'Erro ao salvar as transacoes.',
                    ['insufficient_asset_errors' => $errors],
                    422
                );
            }

            DB::commit();

            return $this->sendResponse([
                'company_transactions' => CompanyTransactionResource::collection($createdCompanyTransactions),
                'treasure_transactions' => TreasureTransactionResource::collection($createdTreasureTransactions),
            ], 'Transacoes criadas com sucesso.');
        } catch (\Throwable $exception) {
            DB::rollBack();

            return $this->sendError(
                'Erro ao salvar as transacoes: ' . $exception->getMessage(),
                [],
                500
            );
        }
    }

    public function update(UpdateTransactionRequest $request, string $type, int $transactionId, ConsolidationService $consolidationService): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            if ($type === 'company') {
                $transaction = CompanyTransaction::with('consolidated.account')
                    ->findOrFail($transactionId);

                if ($transaction->consolidated->account->user_id !== $request->user()->id) {
                    return $this->sendError('Nao autorizado.', [], 403);
                }

                $newData = [
                    'date' => $validated['date'],
                    'operation' => $validated['operation'] === 'buy' ? 'C' : 'V',
                    'quantity' => $validated['quantity'],
                    'price' => $validated['price'],
                    'total_value' => $validated['quantity'] * $validated['price'],
                ];

                $consolidationService->processUpdating($transaction, $newData);

                $transaction->refresh();

                DB::commit();

                return $this->sendResponse(
                    new CompanyTransactionResource($transaction->load('consolidated')),
                    'Transacao atualizada com sucesso.'
                );
            }

            $transaction = TreasureTransaction::with('consolidated.account')
                ->findOrFail($transactionId);

            if ($transaction->consolidated->account->user_id !== $request->user()->id) {
                return $this->sendError('Nao autorizado.', [], 403);
            }

            $newData = [
                'date' => $validated['date'],
                'operation' => $validated['operation'] === 'buy' ? 'C' : 'V',
                'quantity' => $validated['quantity'],
                'invested_value' => $validated['invested_value'],
                'price' => $validated['quantity'] > 0
                    ? $validated['invested_value'] / $validated['quantity']
                    : 0,
            ];

            $consolidationService->processUpdating($transaction, $newData);

            $transaction->refresh();

            DB::commit();

            return $this->sendResponse(
                new TreasureTransactionResource($transaction->load('consolidated')),
                'Transacao atualizada com sucesso.'
            );
        } catch (\Throwable $exception) {
            DB::rollBack();

            return $this->sendError(
                'Erro ao atualizar a transacao: ' . $exception->getMessage(),
                [],
                500
            );
        }
    }

    public function destroy(string $type, int $transactionId, ConsolidationService $consolidationService): JsonResponse
    {
        DB::beginTransaction();

        try {
            if ($type === 'company') {
                $transaction = CompanyTransaction::with('consolidated.account')
                    ->findOrFail($transactionId);

                if ($transaction->consolidated->account->user_id !== auth()->id()) {
                    return $this->sendError('Nao autorizado.', [], 403);
                }

                $consolidationService->processDeleting($transaction);

                DB::commit();

                return $this->sendResponse([], 'Transacao removida com sucesso.');
            }

            $transaction = TreasureTransaction::with('consolidated.account')
                ->findOrFail($transactionId);

            if ($transaction->consolidated->account->user_id !== auth()->id()) {
                return $this->sendError('Nao autorizado.', [], 403);
            }

            $consolidationService->processDeleting($transaction);

            DB::commit();

            return $this->sendResponse([], 'Transacao removida com sucesso.');
        } catch (\Throwable $exception) {
            DB::rollBack();

            return $this->sendError(
                'Erro ao excluir a transacao: ' . $exception->getMessage(),
                [],
                500
            );
        }
    }
}
