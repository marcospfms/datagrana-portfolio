<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta e obrigatoria.',
            'account_id.exists' => 'Conta nao encontrada.',
        ];
    }
}
