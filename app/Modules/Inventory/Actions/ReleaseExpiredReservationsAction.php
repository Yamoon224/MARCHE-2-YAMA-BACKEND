<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Support\Facades\DB;

final class ReleaseExpiredReservationsAction
{
    /** Libère les réservations expirées (celles maintenues n'ont pas d'expiration) ; retourne leur nombre. */
    public function execute(): int
    {
        return DB::transaction(function () {
            $expired = StockReservation::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($expired as $reservation) {
                StockLot::whereKey($reservation->stock_lot_id)->decrement('quantity_reserved', $reservation->quantity);
                $reservation->delete();
            }

            return $expired->count();
        });
    }
}
