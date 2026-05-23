<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Catégories de produits
        $productCategories = [
            ['name' => 'Électronique',    'slug' => 'electronique'],
            ['name' => 'Vêtements',       'slug' => 'vetements'],
            ['name' => 'Alimentation',    'slug' => 'alimentation'],
            ['name' => 'Maison & Jardin', 'slug' => 'maison-jardin'],
            ['name' => 'Sport & Loisirs', 'slug' => 'sport-loisirs'],
        ];

        foreach ($productCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                // On cherche par slug (unique) pour éviter les doublons
                [
                    'name' => $cat['name'],
                    'type' => 'product',
                ]
            );
        }

        // Catégories de services
        $serviceCategories = [
            ['name' => 'Massage & Bien-être',       'slug' => 'massage-bien-etre'],
            ['name' => 'Coiffure & Beauté',         'slug' => 'coiffure-beaute'],
            ['name' => 'Cours & Formation',         'slug' => 'cours-formation'],
            ['name' => 'Artisanat & Réparation',    'slug' => 'artisanat-reparation'],
            ['name' => 'Informatique & Technologie', 'slug' => 'informatique-technologie'],
        ];

        foreach ($serviceCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'type' => 'service',
                ]
            );
        }

        $this->command->info('✅ 10 catégories créées (5 produits + 5 services)');
    }
}
