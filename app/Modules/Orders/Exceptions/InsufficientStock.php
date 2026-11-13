<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use DomainException;

final class InsufficientStock extends DomainException
{
    public static function forProduct(int $productId): self
    {
        return new self("Stock insuffisant pour le produit {$productId}.");
    }
}
