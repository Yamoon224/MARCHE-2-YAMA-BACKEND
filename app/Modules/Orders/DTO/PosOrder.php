<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Vente en caisse créée : montant dû et lignes pour le ticket. */
final class PosOrder
{
    /** @param  list<array{name: string, quantity: int, sale_unit: string, unit_price_minor: int, line_total_minor: int}>  $lines */
    public function __construct(
        public readonly int $orderId,
        public readonly string $number,
        public readonly int $totalMinor,
        public readonly array $lines,
    ) {}
}
