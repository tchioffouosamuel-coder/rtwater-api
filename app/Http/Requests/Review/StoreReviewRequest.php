<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'nullable|string|max:120',
            'content' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required'  => 'La note est obligatoire.',
            'rating.min'       => 'La note doit être entre 1 et 5.',
            'rating.max'       => 'La note doit être entre 1 et 5.',
            'content.required' => 'Le contenu de l\'avis est obligatoire.',
            'content.min'      => 'L\'avis doit contenir au moins 10 caractères.',
            'content.max'      => 'L\'avis ne peut pas dépasser 1000 caractères.',
        ];
    }
}
