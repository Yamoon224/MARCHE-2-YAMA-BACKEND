<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/** Données de démonstration (développement et recette uniquement, jamais en production). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::firstOrCreate(['email' => 'client@marche-yama.test'], [
            'name' => 'Awa Koné', 'phone' => '+33600000001', 'password' => 'password', 'role' => Role::Customer,
        ]);
        User::firstOrCreate(['email' => 'operateur@marche-yama.test'], [
            'name' => 'Opérateur', 'password' => 'password', 'role' => Role::OrderOperator,
        ]);

        User::firstOrCreate(['email' => 'preparateur@marche-yama.test'], [
            'name' => 'Préparateur', 'password' => 'password', 'role' => Role::Picker,
        ]);

        User::firstOrCreate(['email' => 'support@marche-yama.test'], [
            'name' => 'Support client', 'password' => 'password', 'role' => Role::Support,
        ]);

        User::firstOrCreate(['email' => 'livreur@marche-yama.test'], [
            'name' => 'Koffi Livreur', 'password' => 'password', 'role' => Role::Courier,
        ]);

        $warehouse = Warehouse::firstOrCreate(['code' => ReferenceSeeder::WAREHOUSE_CODE], ['name' => 'Entrepôt Paris']);

        foreach (Product::all() as $product) {
            StockLot::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'lot_code' => 'DEMO-'.$product->id],
                ['expires_on' => now()->addDays(14)->toDateString(), 'quantity_on_hand' => $product->is_weight_variable ? 200000 : 500],
            );
        }

        $zone = DeliveryZone::firstOrCreate(['name' => 'Paris (75)'], [
            'fee_minor' => 490, 'free_above_minor' => 5000, 'min_order_minor' => 2000,
        ]);

        foreach ([1, 2, 3] as $day) {
            foreach ([[9, 11], [14, 16], [18, 20]] as [$from, $to]) {
                $start = now()->addDays($day)->setTime($from, 0);
                DeliverySlot::firstOrCreate(
                    ['delivery_zone_id' => $zone->id, 'starts_at' => $start],
                    ['ends_at' => $start->copy()->setTime($to, 0), 'capacity' => 6],
                );
            }
        }
    }
}
