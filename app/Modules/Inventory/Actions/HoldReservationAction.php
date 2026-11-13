<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockReservation;

final class HoldReservationAction
{
    /** Retire l'expiration des réservations d'une référence (commande payée). */
    public function execute(string $reference): void
    {
        StockReservation::where('reference', $reference)->update(['expires_at' => null]);
    }
}
