<?php

namespace App\Http\Requests\Earning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consolidated_id' => [
                'required',
                'integer',
                Rule::exists('consolidated', 'id'),
            ],
            'earning_type_id' => [
                'required',
                'integer',
                Rule::exists('earning_type', 'id'),
            ],
            'company_earning_id' => [
                'nullable',
                'integer',
                Rule::exists('company_earnings', 'id'),
            ],
            'date' => [
                'required',
                'date',
            ],
            'quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
            'net_value' => [
                'required',
                'numeric',
                'min:0',
            ],
            'gross_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'tax' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'imported_with' => [
                'nullable',
                'string',
                Rule::in(['Manual', 'Import', 'Sync']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'consolidated_id.required' => 'A posicao consolidada e obrigatoria.',
            'consolidated_id.exists' => 'Posicao consolidada nao encontrada.',
            'earning_type_id.required' => 'O tipo de provento e obrigatorio.',
            'earning_type_id.exists' => 'Tipo de provento invalido.',
            'company_earning_id.exists' => 'O provento da empresa informado e invalido.',
            'date.required' => 'A data do provento e obrigatoria.',
            'date.date' => 'A data do provento e invalida.',
            'quantity.required' => 'A quantidade e obrigatoria.',
            'quantity.numeric' => 'A quantidade deve ser numerica.',
            'quantity.min' => 'A quantidade nao pode ser negativa.',
            'net_value.required' => 'O valor liquido e obrigatorio.',
            'net_value.numeric' => 'O valor liquido deve ser numerico.',
            'net_value.min' => 'O valor liquido nao pode ser negativo.',
            'gross_value.numeric' => 'O valor bruto deve ser numerico.',
            'gross_value.min' => 'O valor bruto nao pode ser negativo.',
            'tax.numeric' => 'O imposto deve ser numerico.',
            'tax.min' => 'O imposto nao pode ser negativo.',
            'imported_with.in' => 'Origem invalida.',
        ];
    }
}
