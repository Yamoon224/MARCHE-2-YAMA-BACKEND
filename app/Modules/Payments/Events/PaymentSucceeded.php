<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Events\Dispatchable;

/** Déclenche les écritures comptables et les reçus dans les autres modules. */
final class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly PaymentMethod $method,
    ) {}
}
