<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            // 1. D'abord les rôles
            // car UserSeeder a besoin des rôles pour assignRole()

            UserSeeder::class,
            // 2. Ensuite les users
            // car les autres seeders ont besoin des users

            CategorySeeder::class,
            // 3. Les catégories
            // car Products et Services ont besoin des catégories

            ProductSeeder::class,
            // 4. Les produits
            // car BookingSeeder a besoin des services

            ServiceSeeder::class,
            // 5. Les services

            BookingSeeder::class,
            // 6. Enfin les réservations
            // car elles dépendent des users ET des services
        ]);
        // L'ordre est CRUCIAL :
        // Chaque seeder dépend de ceux qui le précèdent
    }
}
