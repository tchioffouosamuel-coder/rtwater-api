<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $services = [
            ['name' => 'Forage puits domestique',              'duration' => 480],
            ['name' => 'Forage puits industriel',              'duration' => 960],
            ['name' => 'Entretien piscine mensuel',            'duration' => 120],
            ['name' => 'Traitement choc piscine',              'duration' => 60],
            ['name' => 'Installation pompe immergée',          'duration' => 240],
            ['name' => 'Installation osmoseur domestique',     'duration' => 120],
            ['name' => 'Maintenance pompe de surface',         'duration' => 90],
            ['name' => 'Audit qualité eau',                    'duration' => 60],
            ['name' => 'Installation adoucisseur eau',         'duration' => 180],
            ['name' => 'Nettoyage château d\'eau',             'duration' => 240],
        ];

        $service = $this->faker->randomElement($services);

        return [
            'category_id' => Category::where('type', 'service')
                ->inRandomOrder()
                ->value('id'),

            'name'             => $service['name'],
            'description'      => $this->faker->paragraphs(2, true),
            'price'            => $this->faker->randomFloat(2, 15000, 500000),

            'duration_minutes' => $service['duration'],

            'is_available'     => $this->faker->boolean(90),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_available' => false,
        ]);
    }
}
