<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Exceptions;

use DomainException;

final class UnbalancedEntry extends DomainException
{
    public static function debitsNotEqualCredits(int $debits, int $credits): self
    {
        return new self("Écriture déséquilibrée : débit {$debits} ≠ crédit {$credits}.");
    }

    public static function invalid(string $why): self
    {
        return new self("Écriture invalide : {$why}.");
    }
}
