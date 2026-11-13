<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Ligne de commande à préparer, telle que vue par le module Fulfillment. */
final readonly class PickSource
{
    public function __construct(
        public int $orderLineId,
        public int $productId,
        public string $name,
        public string $saleUnit,
        public int $quantity,
        public int $unitPriceMinor,
        public string $substitution,
    ) {}
}
