<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivergenceAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'earning_id' => [
                'required',
                'integer',
                Rule::exists('earnings', 'id'),
            ],
            'manter_valores_originais' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'earning_id.required' => 'O provento e obrigatorio.',
            'earning_id.exists' => 'Provento nao encontrado.',
        ];
    }
}
