<?php

namespace Database\Seeders;

use App\Models\Booking;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        Booking::factory(30)->create();
        // 30 réservations avec des statuts variés

        $this->command->info('✅ 30 réservations créées');
    }
}
