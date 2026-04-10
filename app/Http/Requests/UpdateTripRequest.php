<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|min:1|max:255',
            'description' => 'sometimes|string|max:500',
            'calculation_algorithm' => 'sometimes|string|in:optimized,direct',
            'status' => 'sometimes|string|in:open,archived',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 1 caractere.',
            'name.max' => 'O nome não pode exceder 255 caracteres.',
            'description.max' => 'A descrição não pode exceder 500 caracteres.',
            'calculation_algorithm.in' => 'O algoritmo de cálculo deve ser "optimized" ou "direct".',
            'status.in' => 'O status deve ser "open" ou "archived".',
        ];
    }
}
