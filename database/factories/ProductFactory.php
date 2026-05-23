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
                'Smartphone Samsung',
                'iPhone 15',
                'Laptop Dell',
                'Casque Sony',
                'Tablette iPad',
                'Montre connectée',
                'Chargeur USB-C',
                'Clavier mécanique',
                'Souris gaming',
                'Écran 4K',
                'Webcam HD',
                'Disque SSD',
            ]) . ' ' . $this->faker->bothify('?? ##'),

            'description' => $this->faker->paragraphs(2, true),
            'price'       => $this->faker->randomFloat(2, 5, 2000),
            'stock'       => $this->faker->numberBetween(0, 100),

            'image_url'    => null,
            // ✅ image_url au lieu de image

            'is_available' => $this->faker->boolean(85),
            // ✅ is_available au lieu de is_active
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
