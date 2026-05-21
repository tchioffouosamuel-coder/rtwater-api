<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request): Response
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // ✅ Attribuer automatiquement le rôle par défaut
        $defaultRole = Role::getDefault();
        // Role::getDefault() → retourne le rôle avec is_default = true
        // C'est le rôle 'user' qu'on a créé dans le seeder

        if ($defaultRole) {
            $user->assignRole($defaultRole->name);
            // Si un rôle par défaut existe → on l'attribue au nouvel user
            // INSERT INTO role_user (user_id, role_id) VALUES (...)
        }

        event(new Registered($user));
        // Déclenche l'événement "UserRegistered" de Laravel
        // Utilisé pour envoyer l'email de vérification

        Auth::login($user);
        // Connecte l'user immédiatement après l'inscription

        return response()->noContent();
        // Retourne HTTP 204 (No Content) → inscription réussie
    }
}
