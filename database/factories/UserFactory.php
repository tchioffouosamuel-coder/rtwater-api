<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;
    // Variable statique partagée entre toutes les instances
    // Évite de recalculer le hash à chaque user créé
    // Le même hash est réutilisé pour tous les users de test

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            // Génère un nom complet : "Jean Dupont", "Marie Martin"...

            'email'             => $this->faker->unique()->safeEmail(),
            // safeEmail() → génère des emails dans des domaines
            // sûrs pour les tests : example.com, example.net...
            // unique() → garantit qu'aucun email n'est en double

            'email_verified_at' => now(),
            // Tous les users de test ont leur email vérifié
            // now() → date et heure actuelles

            'password'          => static::$password ??= Hash::make('password'),
            // ??= → "null coalescing assignment"
            // Si $password est null → calcule Hash::make('password') ET l'assigne
            // Si $password existe déjà → réutilise la valeur
            // Tous les users de test ont le mot de passe : "password"

            'remember_token'    => Str::random(10),
            // Génère un token aléatoire de 10 caractères
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
            // State pour créer un user avec email non vérifié
            // Utilisation : User::factory()->unverified()->create()
        ]);
    }
}
