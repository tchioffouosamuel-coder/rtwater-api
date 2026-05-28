<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
// FormRequest = classe de base Laravel pour les Form Requests
// Elle étend Request donc elle a toutes ses méthodes
{
    public function authorize(): bool
    {
        return true;
        // authorize() → qui peut soumettre ce formulaire ?
        // true  → tout le monde (routes publiques)
        // false → personne (bloque tout)
        // Pour les routes protégées on mettra une logique ici
    }

    public function rules(): array
    // rules() → définit toutes les règles de validation
    {
        return [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    // messages() → personnalise les messages d'erreur
    // Sans messages() → Laravel utilise ses messages par défaut en anglais
    {
        return [
            'email.required'    => 'L\'adresse email est obligatoire',
            'email.email'       => 'L\'adresse email n\'est pas valide',
            'password.required' => 'Le mot de passe est obligatoire',
        ];
    }
}
