<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory(30)->create();
        // Crée 30 produits avec des données aléatoires réalistes
        // Chaque produit est lié à une catégorie de type 'product'
        // (géré dans la ProductFactory)

        Product::factory(5)->outOfStock()->create();
        // Crée 5 produits en rupture de stock
        // Utilise le state outOfStock() → stock = 0

        Product::factory(3)->inactive()->create();
        // Crée 3 produits inactifs
        // Utilise le state inactive() → is_active = false

        $this->command->info('✅ 38 produits créés (30 normaux + 5 rupture + 3 inactifs)');
    }
}
