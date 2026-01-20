<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class StorePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'month_value' => ['required', 'numeric', 'min:0'],
            'target_value' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do portfolio e obrigatorio.',
            'name.max' => 'O nome deve ter no maximo 80 caracteres.',
            'month_value.required' => 'O valor mensal e obrigatorio.',
            'month_value.min' => 'O valor mensal nao pode ser negativo.',
            'target_value.required' => 'O valor objetivo e obrigatorio.',
            'target_value.min' => 'O valor objetivo nao pode ser negativo.',
        ];
    }
}
