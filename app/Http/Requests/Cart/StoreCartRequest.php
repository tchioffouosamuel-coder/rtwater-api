<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Le produit est obligatoire',
            'product_id.exists'   => 'Ce produit n\'existe pas',
            'quantity.required'   => 'La quantité est obligatoire',
            'quantity.min'        => 'La quantité minimum est 1',
            'quantity.max'        => 'La quantité maximum est 100',
        ];
    }
}
