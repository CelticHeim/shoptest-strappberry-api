<?php

namespace App\Http\Requests\Api\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class GetTransactionStatusRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'payment_id' => 'required|string|min:5',
        ];
    }

    public function messages(): array {
        return [
            'payment_id.required' => 'El ID de pago es requerido',
            'payment_id.string' => 'El ID de pago debe ser texto',
            'payment_id.min' => 'El ID de pago debe tener al menos 5 caracteres',
        ];
    }
}
