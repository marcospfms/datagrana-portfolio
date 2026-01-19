<?php

namespace App\Services\Consolidated;

use App\Exceptions\InsufficientAssetException;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use App\Models\TreasureTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

class ConsolidationService
{
    public function createTransaction($transaction, Consolidated $consolidated, int $transactionIndex = null, array $assetInfo = null): void
    {
        DB::transaction(function () use ($transaction, $consolidated, $transactionIndex, $assetInfo) {
            if ($transaction->operation === 'V' && $consolidated->quantity_current < $transaction->quantity) {
                $errorMessage = $this->buildSaleErrorMessage($consolidated, $transaction, $transactionIndex, $assetInfo);

                throw new InsufficientAssetException(
                    $errorMessage,
                    $transactionIndex,
                    $assetInfo,
                    $consolidated->quantity_current,
                    $transaction->quantity
                );
            }

            $this->applyTransactionToConsolidated($consolidated, $transaction);

            $transaction->save();
            $consolidated->save();
        });
    }

    public function updateTransaction($transaction, array $newData): void
    {
        DB::transaction(function () use ($transaction, $newData) {
            $consolidated = Consolidated::lockForUpdate()->findOrFail($transaction->consolidated_id);

            $transaction->fill($newData);
            $transaction->save();
            $transaction->refresh();

            $this->recalculateConsolidated($consolidated);
        });
    }

    public function deleteTransaction($transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $consolidated = Consolidated::lockForUpdate()->findOrFail($transaction->consolidated_id);

            $transaction->delete();

            $hasTransactions = $this->hasTransactions($consolidated);

            if (!$hasTransactions) {
                $consolidated->earnings()->delete();
                $consolidated->userNetBalances()->delete();
                $consolidated->delete();
            } else {
                $this->recalculateConsolidated($consolidated);
            }
        });
    }

    public function getOrCreateConsolidated(int $accountId, int $companyTickerId = null, int $treasureId = null): Consolidated
    {
        $query = Consolidated::where('account_id', $accountId)
            ->when($companyTickerId, fn ($q) => $q->where('company_ticker_id', $companyTickerId))
            ->when($treasureId, fn ($q) => $q->where('treasure_id', $treasureId))
            ->where('closed', false);

        $consolidated = $query->lockForUpdate()->first();

        if (!$consolidated) {
            $consolidated = Consolidated::create([
                'account_id' => $accountId,
                'company_ticker_id' => $companyTickerId,
                'treasure_id' => $treasureId,
                'average_purchase_price' => 0,
                'quantity_current' => 0,
                'quantity_purchased' => 0,
                'quantity_sold' => 0,
                'total_purchased' => 0,
                'total_sold' => 0,
                'average_selling_price' => 0,
                'closed' => false,
            ]);
        }

        return $consolidated;
    }

    public function recalculateConsolidated(Consolidated $consolidated): void
    {
        $consolidated->fill([
            'average_purchase_price' => 0,
            'quantity_current' => 0,
            'quantity_purchased' => 0,
            'quantity_sold' => 0,
            'total_purchased' => 0,
            'total_sold' => 0,
            'average_selling_price' => 0,
            'closed' => false,
        ]);

        $transactions = collect();

        if ($consolidated->company_ticker_id) {
            $companyTransactions = CompanyTransaction::where('consolidated_id', $consolidated->id)
                ->orderBy('date')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($transaction) => $transaction->fresh());
            $transactions = $transactions->concat($companyTransactions);
        }

        if ($consolidated->treasure_id) {
            $treasureTransactions = TreasureTransaction::where('consolidated_id', $consolidated->id)
                ->orderBy('date')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($transaction) => $transaction->fresh());
            $transactions = $transactions->concat($treasureTransactions);
        }

        $transactions = $transactions->sortBy([
            ['date', 'asc'],
            ['created_at', 'asc'],
        ]);

        foreach ($transactions as $transaction) {
            $this->applyTransactionToConsolidated($consolidated, $transaction);
        }

        $consolidated->save();
    }

    public function processCreating($transaction, Consolidated $consolidated, int $transactionIndex = null, array $assetInfo = null): void
    {
        $this->createTransaction($transaction, $consolidated, $transactionIndex, $assetInfo);
    }

    public function processUpdating($transaction, array $newData): void
    {
        $this->updateTransaction($transaction, $newData);
    }

    public function processDeleting($transaction): void
    {
        $this->deleteTransaction($transaction);
    }

    private function applyTransactionToConsolidated(Consolidated $consolidated, $transaction): void
    {
        if ($transaction->operation === 'C') {
            $this->applyPurchase($consolidated, $transaction);
        } else {
            $this->applySale($consolidated, $transaction);
        }
    }

    private function applyPurchase(Consolidated $consolidated, $transaction): void
    {
        $value = $transaction->total_value ?? $transaction->invested_value;

        $consolidated->quantity_current += $transaction->quantity;
        $consolidated->quantity_purchased += $transaction->quantity;
        $consolidated->total_purchased += $value;

        $consolidated->average_purchase_price = $consolidated->quantity_purchased > 0
            ? ($consolidated->total_purchased / $consolidated->quantity_purchased)
            : 0;
    }

    private function applySale(Consolidated $consolidated, $transaction): void
    {
        $value = $transaction->total_value ?? $transaction->invested_value;

        $consolidated->quantity_current -= $transaction->quantity;
        $consolidated->quantity_sold += $transaction->quantity;
        $consolidated->total_sold += $value;

        if ($consolidated->quantity_current < 0) {
            throw new Exception('Voce nao pode vender mais do que possui');
        }

        $consolidated->average_selling_price = $consolidated->quantity_sold > 0
            ? ($consolidated->total_sold / $consolidated->quantity_sold)
            : 0;

        $consolidated->closed = (bccomp($consolidated->quantity_current, '0', 8) === 0);
    }

    private function hasTransactions(Consolidated $consolidated): bool
    {
        $hasCompanyTransactions = CompanyTransaction::where('consolidated_id', $consolidated->id)->exists();
        $hasTreasureTransactions = TreasureTransaction::where('consolidated_id', $consolidated->id)->exists();

        return $hasCompanyTransactions || $hasTreasureTransactions;
    }

    private function buildSaleErrorMessage(Consolidated $consolidated, $transaction, int $transactionIndex = null, array $assetInfo = null): string
    {
        $assetName = 'Ativo desconhecido';
        $assetCode = '';

        if ($consolidated->company_ticker_id && $assetInfo) {
            if (isset($assetInfo['ticker'])) {
                $assetCode = $assetInfo['ticker']['code'] ?? '';
                $assetName = $assetInfo['company']['name'] ?? $assetCode;
            }
        } elseif ($consolidated->treasure_id && $assetInfo) {
            $assetCode = $assetInfo['code'] ?? '';
            $assetName = $assetInfo['name'] ?? $assetCode;
        }

        $availableFormatted = number_format($consolidated->quantity_current, 2, ',', '.');
        $requestedFormatted = number_format($transaction->quantity, 2, ',', '.');

        $errorMessage = "Inconsistencia na venda de {$assetCode}";
        $errorMessage .= "\n- Ativo: {$assetName}";
        $errorMessage .= "\n- Quantidade solicitada: {$requestedFormatted}";
        $errorMessage .= "\n- Quantidade disponivel: {$availableFormatted}";
        $errorMessage .= "\n- Ajuste a quantidade para {$availableFormatted} ou menos.";

        return $errorMessage;
    }
}
