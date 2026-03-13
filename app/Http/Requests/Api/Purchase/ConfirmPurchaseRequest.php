<?php

namespace App\Http\Requests\Api\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPurchaseRequest extends FormRequest {
    public function authorize(): bool {
        return (bool) auth('api')->user();
    }

    public function rules(): array {
        return [
            'payment_id' => 'required|integer',
            'preference_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
