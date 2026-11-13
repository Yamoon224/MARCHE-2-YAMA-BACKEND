<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

final readonly class InvoiceableOrder
{
    /** @param list<InvoiceableLine> $lines */
    public function __construct(
        public int $orderId,
        public ?int $userId,
        public string $currency,
        public int $deliveryFeeMinor,
        public int $discountMinor,
        public int $totalMinor,
        public array $lines,
    ) {}
}
