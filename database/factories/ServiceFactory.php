<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $services = [
            ['name' => 'Massage suédois',        'duration' => 60],
            ['name' => 'Massage aux pierres',     'duration' => 90],
            ['name' => 'Soin du visage',          'duration' => 45],
            ['name' => 'Coupe homme',             'duration' => 30],
            ['name' => 'Coupe femme + Brushing',  'duration' => 60],
            ['name' => 'Cours de yoga',           'duration' => 60],
            ['name' => 'Cours de guitare',        'duration' => 45],
            ['name' => 'Consultation comptable',  'duration' => 60],
            ['name' => 'Dépannage informatique',  'duration' => 120],
            ['name' => 'Plomberie urgence',       'duration' => 90],
        ];
        // Liste de services réalistes avec leur durée en minutes

        $service = $this->faker->randomElement($services);
        // Choisit un service aléatoire dans la liste

        return [
            'category_id' => Category::where('type', 'service')
                ->inRandomOrder()
                ->value('id'),
            // Catégorie de type 'service' au hasard

            'name'             => $service['name'],
            'description'      => $this->faker->paragraphs(2, true),
            'price'            => $this->faker->randomFloat(2, 15, 300),
            // Prix entre 15€ et 300€

            'duration_minutes' => $service['duration'],
            // Durée cohérente avec le service choisi

            'is_available'     => $this->faker->boolean(90),
            // 90% des services sont disponibles
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_available' => false,
        ]);
    }
}
