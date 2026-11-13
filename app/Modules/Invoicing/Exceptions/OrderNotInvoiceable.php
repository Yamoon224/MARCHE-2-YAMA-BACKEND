<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Exceptions;

use DomainException;

final class OrderNotInvoiceable extends DomainException
{
    public static function notFound(int $orderId): self
    {
        return new self("Commande {$orderId} introuvable : facturation impossible.");
    }
}
