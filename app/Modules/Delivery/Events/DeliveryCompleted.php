<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Commande remise au client (code confirmé) ; porte l'argent encaissé en espèces, le cas échéant. */
final class DeliveryCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $courierId,
        public readonly int $cashCollectedMinor,
    ) {}
}
