<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            // Password::min(8) → minimum 8 caractères
            // confirmed → password_confirmation doit correspondre
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Le nom est obligatoire',
            'email.required'     => 'L\'adresse email est obligatoire',
            'email.unique'       => 'Cette adresse email est déjà utilisée',
            'password.required'  => 'Le mot de passe est obligatoire',
            'password.confirmed' => 'Les mots de passe ne correspondent pas',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères',
        ];
    }
}
