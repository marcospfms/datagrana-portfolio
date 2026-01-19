<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BankResource;
use App\Models\Account;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends BaseController
{
    public function banks(): JsonResponse
    {
        $banks = Bank::active()
            ->orderBy('name')
            ->get();

        return $this->sendResponse(BankResource::collection($banks));
    }

    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->accounts()
            ->with('bank')
            ->orderBy('default', 'desc')
            ->orderBy('nickname')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(AccountResource::collection($accounts));
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        if ($request->boolean('default')) {
            $request->user()->accounts()->update(['default' => false]);
        }

        $isFirstAccount = $request->user()->accounts()->count() === 0;

        $account = $request->user()->accounts()->create([
            ...$request->validated(),
            'default' => $request->boolean('default') || $isFirstAccount,
        ]);

        return $this->sendResponse(
            new AccountResource($account->load('bank')),
            'Conta criada com sucesso.'
        );
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return $this->sendResponse(
            new AccountResource($account->load('bank'))
        );
    }

    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        if ($request->boolean('default')) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['default' => false]);
        }

        $account->update($request->validated());

        return $this->sendResponse(
            new AccountResource($account->fresh()->load('bank')),
            'Conta atualizada com sucesso.'
        );
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->hasActivePositions()) {
            return $this->sendError(
                'Nao e possivel excluir uma conta com posicoes ativas. Encerre ou transfira as posicoes primeiro.',
                [],
                409
            );
        }

        $wasDefault = $account->default;
        $userId = $account->user_id;

        $account->delete();

        if ($wasDefault) {
            Account::where('user_id', $userId)
                ->orderBy('created_at')
                ->first()
                ?->update(['default' => true]);
        }

        return $this->sendResponse([], 'Conta removida com sucesso.');
    }
}
