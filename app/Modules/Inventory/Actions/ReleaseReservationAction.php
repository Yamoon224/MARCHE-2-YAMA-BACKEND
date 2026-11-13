<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Support\Facades\DB;

final class ReleaseReservationAction
{
    /** Libère les réservations d'une référence. Sans effet si elles n'existent plus (idempotent). */
    public function execute(string $reference): void
    {
        DB::transaction(function () use ($reference) {
            foreach (StockReservation::where('reference', $reference)->lockForUpdate()->get() as $reservation) {
                StockLot::whereKey($reservation->stock_lot_id)->decrement('quantity_reserved', $reservation->quantity);
                $reservation->delete();
            }
        });
    }
}
