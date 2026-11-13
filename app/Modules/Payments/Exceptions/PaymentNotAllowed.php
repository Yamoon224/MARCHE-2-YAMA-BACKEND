<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use DomainException;

final class PaymentNotAllowed extends DomainException
{
    public static function orderNotFound(): self
    {
        return new self('Commande introuvable.');
    }

    public static function orderNotPayable(string $status): self
    {
        return new self("La commande ne peut plus être payée (statut : {$status}).");
    }

    public static function notCashOnDelivery(): self
    {
        return new self('Ce paiement n\'est pas un paiement à la livraison en attente.');
    }
}
