<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::where('type', 'product')
                ->inRandomOrder()
                ->value('id'),

            'name' => $this->faker->randomElement([
                'Pompe immergée Grundfos',
                'Pompe de surface Pedrollo',
                'Filtre à sable piscine',
                'Osmoseur domestique 5 étages',
                'Chlore choc granulés 5kg',
                'Hypochlorite de calcium 65%',
                'Traitement anti-algues piscine',
                'Pompe doseuse automatique',
                'Adoucisseur d\'eau 25L',
                'Cartouche filtrante 10 pouces',
                'UV stérilisateur 40W',
                'Compteur eau volumétrique',
                'Robinet flotteur laiton',
                'Tuyau PEHD 32mm rouleau 100m',
                'Groupe motopompe diesel 3 pouces',
            ]) . ' ' . $this->faker->bothify('## ##'),

            'description' => $this->faker->paragraphs(2, true),
            'price'       => $this->faker->randomFloat(2, 5000, 850000),
            'stock'       => $this->faker->numberBetween(0, 50),

            'image_url'   => null,

            'is_available' => $this->faker->boolean(85),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_available' => false,
            // ✅ is_available au lieu de is_active
        ]);
    }
}
