<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories,slug|max:255',
            'type' => 'required|in:product,service',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'slug.required' => 'Le slug est obligatoire',
            'slug.unique'   => 'Ce slug est déjà utilisé',
            'type.required' => 'Le type est obligatoire',
            'type.in'       => 'Le type doit être "product" ou "service"',
        ];
    }
}
