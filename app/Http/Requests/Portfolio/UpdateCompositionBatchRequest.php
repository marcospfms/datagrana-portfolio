<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompositionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'compositions' => ['required', 'array', 'min:1'],
            'compositions.*.id' => ['required', 'integer', 'exists:compositions,id'],
            'compositions.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'compositions.required' => 'Informe pelo menos uma composicao.',
            'compositions.*.id.required' => 'A composicao e obrigatoria.',
            'compositions.*.id.exists' => 'Composicao nao encontrada.',
            'compositions.*.percentage.required' => 'A porcentagem e obrigatoria.',
            'compositions.*.percentage.min' => 'A porcentagem nao pode ser negativa.',
            'compositions.*.percentage.max' => 'A porcentagem nao pode ser maior que 100%.',
        ];
    }
}
