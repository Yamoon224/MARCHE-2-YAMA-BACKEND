<?php

namespace Database\Seeders;

use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Données de référence nécessaires au fonctionnement : entrepôt et zones de livraison de Paris et de l'Île-de-France
 * (montants en centimes d'euro). Sûr en production : ne crée que ce qui manque. Les lots de stock et les créneaux
 * se saisissent depuis le backoffice.
 */
class ReferenceSeeder extends Seeder
{
    public const WAREHOUSE_CODE = 'PARIS-01';

    public function run(): void
    {
        Warehouse::firstOrCreate(['code' => self::WAREHOUSE_CODE], ['name' => 'Entrepôt Paris']);

        foreach ([
            ['Paris (75)', 490, 5000, 2000],
            ['Petite couronne (92, 93, 94)', 690, 6000, 3000],
            ['Grande couronne (77, 78, 91, 95)', 990, 8000, 4000],
        ] as [$name, $fee, $freeAbove, $minimum]) {
            DeliveryZone::firstOrCreate(['name' => $name], [
                'fee_minor' => $fee, 'free_above_minor' => $freeAbove, 'min_order_minor' => $minimum,
            ]);
        }
    }
}