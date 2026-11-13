<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

final readonly class PayableOrder
{
    public function __construct(
        public int $orderId,
        public ?int $userId,
        public int $totalMinor,
        public string $currency,
        public string $status,
        public bool $acceptsPayment,
        /** Montant réel après préparation (pesée, ruptures) ; égal au total tant que non préparée. */
        public int $finalTotalMinor = 0,
    ) {}
}
