<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Commande vue par le module Delivery : où, chez qui, quand, et combien. */
final readonly class DeliverableOrder
{
    /** @param array<string, mixed>|null $address */
    public function __construct(
        public int $orderId,
        public ?int $userId,
        public string $fulfillment,
        public string $status,
        public ?array $address,
        public ?string $phone,
        public ?int $slotId,
        public int $finalTotalMinor,
        public string $currency,
    ) {}
}
