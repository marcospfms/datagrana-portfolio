<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'percentage.required' => 'A porcentagem e obrigatoria.',
            'percentage.min' => 'A porcentagem nao pode ser negativa.',
            'percentage.max' => 'A porcentagem nao pode ser maior que 100%.',
        ];
    }
}
