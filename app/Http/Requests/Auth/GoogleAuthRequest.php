<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'min:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_token.required' => 'O token do Google e obrigatorio.',
            'id_token.string' => 'O token do Google deve ser uma string.',
            'id_token.min' => 'O token do Google parece ser invalido.',
        ];
    }
}
