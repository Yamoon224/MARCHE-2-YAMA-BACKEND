<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

/** Les prix du catalogue sont TTC : la TVA est extraite du montant, en entiers, arrondie à l'unité la plus proche. */
final class VatCalculator
{
    public function vatIncluded(int $grossMinor, int $rateBp): int
    {
        if ($rateBp === 0) {
            return 0;
        }

        $divisor = 10000 + $rateBp;
        $net = intdiv($grossMinor * 10000 + intdiv($divisor, 2), $divisor);

        return $grossMinor - $net;
    }
}
