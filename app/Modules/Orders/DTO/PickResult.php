<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Résultat de préparation d'une ligne : quantité réellement servie, éventuellement un produit de remplacement. */
final readonly class PickResult
{
    public function __construct(
        public int $orderLineId,
        public int $productId,
        public string $name,
        public int $quantity,
        public int $unitPriceMinor,
        public bool $substituted,
    ) {}
}
