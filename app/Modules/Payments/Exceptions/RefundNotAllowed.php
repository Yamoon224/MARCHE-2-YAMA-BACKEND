<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use DomainException;

final class RefundNotAllowed extends DomainException
{
    public static function exceedsRefundable(int $refundable): self
    {
        return new self("Montant supérieur au remboursable ({$refundable}).");
    }

    public static function nothingToRefund(): self
    {
        return new self('Aucun paiement encaissé à rembourser pour cette commande.');
    }
}
