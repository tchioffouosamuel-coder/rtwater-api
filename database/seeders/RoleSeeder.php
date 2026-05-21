<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Role::firstOrCreate(['name' => '...'], [...])
        // firstOrCreate → cherche d'abord si le rôle existe déjà
        // Si oui → ne rien faire
        // Si non → le créer
        // Évite les doublons si on lance le seeder plusieurs fois

        Role::firstOrCreate(
            ['name' => Role::ADMIN],
            // Premier argument : les colonnes pour chercher
            // On cherche un rôle avec name = 'admin'
            [
                // Deuxième argument : les données à insérer si pas trouvé
                'label'       => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'is_default'  => false,
            ]
        );

        Role::firstOrCreate(
            ['name' => Role::GESTIONNAIRE],
            [
                'label'       => 'Gestionnaire',
                'description' => 'Peut gérer les produits, services et commandes',
                'is_default'  => false,
            ]
        );

        Role::firstOrCreate(
            ['name' => Role::SECRETAIRE],
            [
                'label'       => 'Secrétaire',
                'description' => 'Peut gérer les réservations et les clients',
                'is_default'  => false,
            ]
        );

        Role::firstOrCreate(
            ['name' => Role::COMPTABLE],
            [
                'label'       => 'Comptable',
                'description' => 'Peut consulter les commandes et les paiements',
                'is_default'  => false,
            ]
        );

        Role::firstOrCreate(
            ['name' => Role::USER],
            [
                'label'       => 'Utilisateur',
                'description' => 'Client standard, peut acheter et réserver',
                'is_default'  => true,
                // Ce rôle est attribué automatiquement à tout nouvel inscrit
            ]
        );
    }
}
