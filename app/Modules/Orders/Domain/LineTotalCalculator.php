<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

/**
 * Total de ligne en entiers. Les quantités sont dans l'unité de base :
 * pièces, grammes (vente au kg) ou millilitres (vente au litre).
 */
final class LineTotalCalculator
{
    public function total(string $saleUnit, int $unitPriceMinor, int $quantity): int
    {
        if ($saleUnit === 'piece') {
            return $unitPriceMinor * $quantity;
        }

        // Prix au kg / au litre : arrondi à l'unité monétaire la plus proche, sans flottants.
        return intdiv($unitPriceMinor * $quantity + 500, 1000);
    }
}
