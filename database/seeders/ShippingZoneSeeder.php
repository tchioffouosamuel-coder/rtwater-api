<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Zones de livraison de depart. A ajuster dans le backoffice : ce sont des
 * valeurs plausibles pour Yaounde, pas des tarifs contractuels.
 */
class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name'        => 'Yaoundé — centre',
                'description' => 'Centre-ville, Bastos, Nlongkak, Mvog-Mbi, Essos',
                'fee'         => 1000,
                'free_above'  => 25000,
                'delay'       => 'Sous 24 h',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Yaoundé — périphérie',
                'description' => 'Nkolbisson, Odza, Mfandena, Simbock, Ahala',
                'fee'         => 2000,
                'free_above'  => 40000,
                'delay'       => '24 à 48 h',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Retrait sur place',
                'description' => 'À récupérer à notre dépôt, aux heures d’ouverture',
                'fee'         => 0,
                'free_above'  => null,
                'delay'       => 'Dès validation du paiement',
                'sort_order'  => 0,
            ],
            [
                'name'        => 'Autre ville (sur devis)',
                'description' => 'Douala, Bafoussam et reste du pays — nous vous rappelons pour le transport',
                'fee'         => 5000,
                'free_above'  => null,
                'delay'       => '3 à 5 jours',
                'sort_order'  => 3,
            ],
        ];

        foreach ($zones as $zone) {
            ShippingZone::firstOrCreate(['name' => $zone['name']], $zone);
        }
    }
}
