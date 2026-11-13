<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Foundation\Events\Dispatchable;

/** Événement de domaine : déclenche stock, comptabilité et notifications dans les autres modules. */
final class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}
}
