<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Un livreur a remis ses espèces à la caisse : attendu, compté (l'écart est compté - attendu). */
final class CashHandedOver
{
    use Dispatchable;

    public function __construct(
        public readonly int $handoverId,
        public readonly int $courierId,
        public readonly int $expectedMinor,
        public readonly int $countedMinor,
    ) {}
}
