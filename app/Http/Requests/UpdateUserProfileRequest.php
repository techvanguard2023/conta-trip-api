<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'name' => 'sometimes|string|min:3|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'sometimes|string|min:10|max:20',
            'pix_key' => 'nullable|string|max:255',
            'pixKey' => 'nullable|string|max:255', // Support camelCase
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres.',
            'name.max' => 'O nome não pode exceder 255 caracteres.',
            'email.email' => 'O email deve ser um endereço válido.',
            'email.unique' => 'Este email já está registrado.',
            'phone.min' => 'O telefone deve ter pelo menos 10 caracteres.',
            'phone.max' => 'O telefone não pode exceder 20 caracteres.',
            'pix_key.max' => 'A chave PIX não pode exceder 255 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Mapear pixKey para pix_key se enviado em camelCase
        if ($this->has('pixKey') && !$this->has('pix_key')) {
            $this->merge([
                'pix_key' => $this->input('pixKey'),
            ]);
        }
    }
}
