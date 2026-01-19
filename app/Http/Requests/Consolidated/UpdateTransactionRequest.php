<?php

namespace App\Http\Requests\Consolidated;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('type')) {
            $this->merge(['type' => $this->route('type')]);
        }
    }

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
            'date' => ['required', 'date'],
            'operation' => ['required', 'in:buy,sell'],
            'quantity' => ['required', 'numeric', 'min:0.00000001'],
            'price' => ['required_if:type,company', 'numeric', 'min:0.00000001'],
            'company_ticker_id' => [
                'required_if:type,company',
                'integer',
                Rule::exists('company_tickers', 'id')->where('status', true),
            ],
            'treasure_id' => [
                'required_if:type,treasure',
                'integer',
                Rule::exists('treasures', 'id')->where('status', true),
            ],
            'invested_value' => ['required_if:type,treasure', 'numeric', 'min:0.00000001'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta e obrigatoria.',
            'account_id.exists' => 'Conta nao encontrada ou nao pertence a voce.',
            'date.required' => 'A data da transacao e obrigatoria.',
            'operation.in' => 'Operacao invalida.',
            'quantity.required' => 'A quantidade e obrigatoria.',
            'price.required_if' => 'O preco e obrigatorio para acoes.',
            'company_ticker_id.required_if' => 'O ativo e obrigatorio para acoes.',
            'treasure_id.required_if' => 'O titulo e obrigatorio para tesouros.',
            'invested_value.required_if' => 'O valor investido e obrigatorio para tesouros.',
        ];
    }
}
