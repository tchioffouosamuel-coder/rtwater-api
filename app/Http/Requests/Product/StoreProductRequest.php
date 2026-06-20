<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
        // Seul l'admin peut créer un produit
        // $this->user() → l'utilisateur connecté
        // isAdmin() → méthode qu'on a définie dans le Model User
        // Si false → Laravel retourne automatiquement 403 Forbidden
    }

    public function rules(): array
    {
        return [
            'category_id'  => 'required|exists:categories,id',
            'name'         => 'required|string|max:255',
            'sku'          => 'nullable|string|max:100|unique:products,sku',
            'description'  => 'nullable|string',
            'price'        => 'required|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'stock'        => 'required|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'location'     => 'nullable|string|max:255',
            'image_url'    => 'nullable|image|max:2048',
            'is_available' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'La catégorie est obligatoire',
            'category_id.exists'   => 'La catégorie sélectionnée n\'existe pas',
            'name.required'        => 'Le nom du produit est obligatoire',
            'price.required'       => 'Le prix est obligatoire',
            'price.numeric'        => 'Le prix doit être un nombre',
            'price.min'            => 'Le prix ne peut pas être négatif',
            'stock.required'       => 'Le stock est obligatoire',
            'stock.integer'        => 'Le stock doit être un nombre entier',
            'image_url.image'      => 'Le fichier doit être une image',
            'image_url.max'        => 'L\'image ne doit pas dépasser 2 Mo',
        ];
    }
}
