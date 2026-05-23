<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        Service::factory(20)->create();
        // 20 services disponibles

        Service::factory(3)->unavailable()->create();
        // 3 services temporairement indisponibles

        $this->command->info('✅ 23 services créés (20 disponibles + 3 indisponibles)');
    }
}
