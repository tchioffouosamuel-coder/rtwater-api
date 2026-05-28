<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;
        // $this->route('category') → récupère l'objet Category
        // injecté par le Route Model Binding
        // ->id → son identifiant

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:categories,slug,' . $categoryId,
            // unique:categories,slug,{id}
            // → slug unique SAUF pour cette catégorie elle-même
            'type' => 'sometimes|in:product,service',
        ];
    }
}
