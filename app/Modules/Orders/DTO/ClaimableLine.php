<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Ligne telle que livrée (quantité et montant réels après préparation), pour contrôler une réclamation. */
final readonly class ClaimableLine
{
    public function __construct(
        public int $orderLineId,
        public int $productId,
        public string $name,
        public string $saleUnit,
        public int $quantity,
        public int $lineTotalMinor,
    ) {}
}
