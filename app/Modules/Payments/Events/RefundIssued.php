<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\RefundKind;
use Illuminate\Foundation\Events\Dispatchable;

/** L'argent est rendu au client : déclenche l'écriture comptable (et l'avoir pour un geste commercial). */
final class RefundIssued
{
    use Dispatchable;

    public function __construct(
        public readonly int $refundId,
        public readonly int $orderId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly PaymentMethod $method,
        public readonly RefundKind $kind,
    ) {}
}
