<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Créer l'admin principal ──────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            // Cherche par email → évite les doublons

            [
                'name'     => 'Administrateur',
                'password' => Hash::make('password'),
                // Hash::make() → hashe le mot de passe avec bcrypt
                // Ne JAMAIS stocker un mot de passe en clair !
                // 'password' → hashé en '$2y$10$abc...'
            ]
        );
        $admin->assignRole(Role::ADMIN);
        // Attribue le rôle admin à cet utilisateur

        // ── Créer un gestionnaire ────────────────────────────
        $gestionnaire = User::firstOrCreate(
            ['email' => 'gestionnaire@example.com'],
            [
                'name'     => 'Marie Gestionnaire',
                'password' => Hash::make('password'),
            ]
        );
        $gestionnaire->assignRole(Role::GESTIONNAIRE);

        // ── Créer une secrétaire ─────────────────────────────
        $secretaire = User::firstOrCreate(
            ['email' => 'secretaire@example.com'],
            [
                'name'     => 'Sophie Secrétaire',
                'password' => Hash::make('password'),
            ]
        );
        $secretaire->assignRole(Role::SECRETAIRE);

        // ── Créer un comptable ───────────────────────────────
        $comptable = User::firstOrCreate(
            ['email' => 'comptable@example.com'],
            [
                'name'     => 'Paul Comptable',
                'password' => Hash::make('password'),
            ]
        );
        $comptable->assignRole(Role::COMPTABLE);

        // ── Créer des clients de test ────────────────────────
        $users = User::factory(10)->create();
        // factory(10) → crée 10 users avec des données aléatoires
        // UserFactory utilise Faker pour générer nom, email, password...
        // ->create() → insère en DB et retourne une Collection

        foreach ($users as $user) {
            $user->assignRole(Role::USER);
            // Attribue le rôle 'user' à chaque client créé
        }

        $this->command->info('✅ Users créés : admin, gestionnaire, secrétaire, comptable + 10 clients');
        // $this->command->info() → affiche un message dans le terminal
        // pendant l'exécution du seeder
    }
}
