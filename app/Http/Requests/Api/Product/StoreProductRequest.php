<?php

namespace App\Http\Requests\Api\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array {
        return [
            'image.max' => 'La imagen no debe exceder 5MB',
            'image.mimes' => 'La imagen debe ser JPEG, PNG o WebP',
            'image.image' => 'El archivo debe ser una imagen válida',
        ];
    }
}
