<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')->where('status', true),
            ],
            'account' => [
                'required',
                'string',
                'max:200',
                Rule::unique('accounts')->where('user_id', $this->user()->id),
            ],
            'nickname' => [
                'nullable',
                'string',
                'max:50',
            ],
            'default' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.exists' => 'Banco/corretora selecionado nao existe ou esta inativo.',
            'account.required' => 'O numero da conta e obrigatorio.',
            'account.unique' => 'Voce ja possui uma conta com este numero.',
            'account.max' => 'O numero da conta deve ter no maximo 200 caracteres.',
            'nickname.max' => 'O apelido deve ter no maximo 50 caracteres.',
        ];
    }
}
