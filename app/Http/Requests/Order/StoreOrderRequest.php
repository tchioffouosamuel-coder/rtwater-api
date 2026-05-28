<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // Tout utilisateur connecté peut créer une commande
    }

    public function rules(): array
    {
        return [
            'address' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'L\'adresse de livraison est obligatoire',
            'address.max'      => 'L\'adresse ne doit pas dépasser 500 caractères',
        ];
    }
}
