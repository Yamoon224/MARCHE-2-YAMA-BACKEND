<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

final class ReceiveStockAction
{
    /** Enregistre une réception (nouveau lot ou complément d'un lot existant) et son mouvement. */
    public function execute(int $warehouseId, int $productId, string $lotCode, ?string $expiresOn, int $quantity, ?int $userId = null): StockLot
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité reçue doit être positive.');
        }

        return DB::transaction(function () use ($warehouseId, $productId, $lotCode, $expiresOn, $quantity, $userId) {
            $lot = StockLot::query()
                ->where(['warehouse_id' => $warehouseId, 'product_id' => $productId, 'lot_code' => $lotCode])
                ->lockForUpdate()
                ->first();

            if ($lot === null) {
                $lot = StockLot::create([
                    'warehouse_id' => $warehouseId, 'product_id' => $productId, 'lot_code' => $lotCode,
                    'expires_on' => $expiresOn, 'quantity_on_hand' => $quantity,
                ]);
            } else {
                $lot->increment('quantity_on_hand', $quantity);
            }

            StockMovement::create([
                'stock_lot_id' => $lot->id, 'type' => 'receipt', 'quantity' => $quantity,
                'reason' => 'Réception', 'user_id' => $userId,
            ]);

            return $lot->refresh();
        });
    }
}
