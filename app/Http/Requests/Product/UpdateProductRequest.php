<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'category_id'  => 'sometimes|exists:categories,id',
            'name'         => 'sometimes|string|max:255',
            'sku'          => 'nullable|string|max:100|unique:products,sku,' . $this->route('product')?->id,
            'description'  => 'nullable|string',
            'price'        => 'sometimes|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'stock'        => 'sometimes|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'location'     => 'nullable|string|max:255',
            'image_url'    => 'nullable|image|max:2048',
            'is_available' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas',
            'price.numeric'      => 'Le prix doit être un nombre',
            'price.min'          => 'Le prix ne peut pas être négatif',
            'stock.integer'      => 'Le stock doit être un nombre entier',
            'image_url.image'    => 'Le fichier doit être une image',
            'image_url.max'      => 'L\'image ne doit pas dépasser 2 Mo',
        ];
    }
}
