<?php

namespace Database\Seeders;

use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Données de référence nécessaires au fonctionnement (magasin, première zone de livraison). Sûr en production :
 * ne crée que ce qui manque. Les lots de stock et les créneaux se saisissent depuis le backoffice.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['code' => 'MG-PLATEAU'], ['name' => 'Magasin Plateau']);

        DeliveryZone::firstOrCreate(['name' => 'Cocody'], [
            'fee_minor' => 1500, 'free_above_minor' => 20000, 'min_order_minor' => 2000,
        ]);
    }
}
