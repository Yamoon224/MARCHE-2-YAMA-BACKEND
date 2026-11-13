<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Support\Facades\DB;

final class CommitReservationAction
{
    /** Sortie physique : retire la quantité du stock et de la réserve, avec un mouvement de vente par lot. */
    public function execute(string $reference): void
    {
        DB::transaction(function () use ($reference) {
            foreach (StockReservation::where('reference', $reference)->lockForUpdate()->get() as $reservation) {
                $lot = StockLot::whereKey($reservation->stock_lot_id)->lockForUpdate()->firstOrFail();
                $lot->update([
                    'quantity_on_hand' => $lot->quantity_on_hand - $reservation->quantity,
                    'quantity_reserved' => $lot->quantity_reserved - $reservation->quantity,
                ]);

                StockMovement::create([
                    'stock_lot_id' => $lot->id, 'type' => 'sale', 'quantity' => -$reservation->quantity,
                    'reason' => $reference,
                ]);
                $reservation->delete();
            }
        });
    }
}
