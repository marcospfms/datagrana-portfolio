<?php

namespace App\Http\Requests\Consolidated;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accountId = $this->input('account_id');
        $account = $accountId ? Account::find($accountId) : null;

        return $account && $account->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.type' => ['required', 'in:company,treasure'],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.operation' => ['required', 'in:buy,sell'],
            'transactions.*.quantity' => ['required', 'numeric', 'min:0.00000001'],
            'transactions.*.price' => ['required_if:transactions.*.type,company', 'numeric', 'min:0.00000001'],
            'transactions.*.company_ticker_id' => [
                'required_if:transactions.*.type,company',
                'integer',
                Rule::exists('company_tickers', 'id')->where('status', true),
            ],
            'transactions.*.treasure_id' => [
                'required_if:transactions.*.type,treasure',
                'integer',
                Rule::exists('treasures', 'id')->where('status', true),
            ],
            'transactions.*.invested_value' => [
                'required_if:transactions.*.type,treasure',
                'numeric',
                'min:0.00000001',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta e obrigatoria.',
            'account_id.exists' => 'Conta nao encontrada ou nao pertence a voce.',
            'transactions.required' => 'As transacoes sao obrigatorias.',
            'transactions.*.type.in' => 'Tipo de transacao invalido.',
            'transactions.*.date.required' => 'A data da transacao e obrigatoria.',
            'transactions.*.operation.in' => 'Operacao invalida.',
            'transactions.*.quantity.required' => 'A quantidade e obrigatoria.',
            'transactions.*.price.required_if' => 'O preco e obrigatorio para acoes.',
            'transactions.*.company_ticker_id.required_if' => 'O ativo e obrigatorio para acoes.',
            'transactions.*.treasure_id.required_if' => 'O titulo e obrigatorio para tesouros.',
            'transactions.*.invested_value.required_if' => 'O valor investido e obrigatorio para tesouros.',
        ];
    }
}
