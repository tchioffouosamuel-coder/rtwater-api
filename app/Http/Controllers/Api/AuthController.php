<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    // RegisterRequest remplace Request
    // La validation est faite automatiquement avant d'entrer ici
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $defaultRole = Role::getDefault();
        if ($defaultRole) {
            $user->assignRole($defaultRole->name);
        }

        /** @var \App\Models\User $user */
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    // LoginRequest remplace Request
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load('roles');

        return response()->json(['data' => $this->profilePayload($user)]);
    }

    /** PUT /api/me — mise à jour des informations du client connecté */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone'   => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour',
            'data'    => $this->profilePayload($user->load('roles')),
        ]);
    }

    /** PUT /api/me/password — changement de mot de passe */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mot de passe actuel incorrect',
                'errors'  => ['current_password' => ['Mot de passe actuel incorrect']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Révoque les autres sessions, conserve le jeton courant
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['message' => 'Mot de passe modifié']);
    }

    /** POST /api/forgot-password — envoie le lien de réinitialisation */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Même réponse que l'email existe ou non (anti-énumération des comptes)
        return response()->json([
            'message' => "Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.",
        ]);
    }

    /** POST /api/reset-password — définit le nouveau mot de passe */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Ce lien est invalide ou a expiré. Faites une nouvelle demande.',
            ], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.']);
    }

    private function profilePayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'address'    => $user->address,
            'roles'      => $user->roles->pluck('name'),
            'created_at' => $user->created_at->format('d/m/Y'),
        ];
    }
}
