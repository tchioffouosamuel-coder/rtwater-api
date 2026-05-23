<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->value('id'),
            // User aléatoire existant en DB

            'service_id' => Service::inRandomOrder()->value('id'),
            // Service aléatoire existant en DB

            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
            // dateTimeBetween(min, max) → date entre demain et dans 3 mois
            // '+1 day'    → demain
            // '+3 months' → dans 3 mois
            // Toutes les réservations sont dans le futur

            'status' => $this->faker->randomElement([
                'pending',
                'confirmed',
                'cancelled',
                'completed'
            ]),

            'notes' => $this->faker->boolean(40)
                ? $this->faker->sentence()
                : null,
            // 40% de chance d'avoir une note
            // 60% → null (pas de note)
            // boolean(40) → true 40% du temps
        ];
    }
}
