<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Catégories de produits RT Water
        $productCategories = [
            ['name' => 'Pompes à eau',            'slug' => 'pompes-a-eau'],
            ['name' => 'Purification & filtres',   'slug' => 'purification-filtres'],
            ['name' => 'Désinfectants',            'slug' => 'desinfectants'],
            ['name' => 'Équipements piscine',      'slug' => 'equipements-piscine'],
            ['name' => 'Accessoires & pièces',     'slug' => 'accessoires-pieces'],
        ];

        foreach ($productCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'type' => 'product']
            );
        }

        // Catégories de services RT Water
        $serviceCategories = [
            ['name' => 'Forage',                  'slug' => 'forage'],
            ['name' => 'Entretien piscines',       'slug' => 'entretien-piscines'],
            ['name' => 'Maintenance équipements',  'slug' => 'maintenance-equipements'],
            ['name' => 'Installation',             'slug' => 'installation'],
            ['name' => 'Audit & conseil',          'slug' => 'audit-conseil'],
        ];

        foreach ($serviceCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'type' => 'service']
            );
        }

        $this->command->info('✅ 10 catégories RT Water créées (5 produits + 5 services)');
    }
}
