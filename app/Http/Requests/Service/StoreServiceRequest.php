<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'category_id'      => 'required|exists:categories,id',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'is_available'     => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'      => 'La catégorie est obligatoire',
            'category_id.exists'        => 'La catégorie n\'existe pas',
            'name.required'             => 'Le nom du service est obligatoire',
            'price.required'            => 'Le prix est obligatoire',
            'duration_minutes.required' => 'La durée est obligatoire',
            'duration_minutes.min'      => 'La durée minimum est de 15 minutes',
            'duration_minutes.max'      => 'La durée maximum est de 480 minutes (8h)',
        ];
    }
}
