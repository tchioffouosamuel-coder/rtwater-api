<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            // On appelle le RoleSeeder ici
            // Si tu ajoutes d'autres seeders plus tard, tu les listes ici
            // dans l'ordre où ils doivent être exécutés
        ]);
    }
}
