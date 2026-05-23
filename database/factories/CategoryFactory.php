<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
// Factory = classe de base Laravel pour générer des données
// $this->faker = instance de Faker, disponible automatiquement
{
    public function definition(): array
    // definition() → retourne le tableau des données par défaut
    // Appelé chaque fois qu'on crée une instance avec la factory
    {
        $type = $this->faker->randomElement(['product', 'service']);
        // randomElement() → choisit aléatoirement un élément dans le tableau
        // Chaque catégorie sera soit 'product' soit 'service'

        $productCategories = [
            'Électronique',
            'Vêtements',
            'Alimentation',
            'Maison & Jardin',
            'Sport & Loisirs',
            'Beauté & Santé',
        ];
        // Liste de noms réalistes pour les catégories de produits

        $serviceCategories = [
            'Massage & Bien-être',
            'Coiffure & Beauté',
            'Cours & Formation',
            'Plomberie & Électricité',
            'Informatique',
            'Comptabilité',
        ];
        // Liste de noms réalistes pour les catégories de services

        $name = $type === 'product'
            ? $this->faker->randomElement($productCategories)
            : $this->faker->randomElement($serviceCategories);
        // Opérateur ternaire :
        // Si type = 'product' → choisit dans productCategories
        // Si type = 'service' → choisit dans serviceCategories

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            // Str::slug() → convertit le nom en slug URL-friendly
            // "Électronique" → "electronique"
            // "Massage & Bien-être" → "massage-bien-etre"
            //
            // On ajoute un nombre unique à la fin pour éviter les doublons
            // car plusieurs catégories pourraient avoir le même nom
            // "electronique-1234"
            //
            // unique() → garantit que chaque nombre généré est différent
            // dans cette session de génération

            'type' => $type,
        ];
    }
}
