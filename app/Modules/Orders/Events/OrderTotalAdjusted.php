<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Le montant réel diffère du montant payé (pesée, rupture, substitution) : avoir ou régularisation à traiter. */
final class OrderTotalAdjusted
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $chargedMinor,
        public readonly int $finalMinor,
        public readonly int $deltaMinor,
    ) {}
}
