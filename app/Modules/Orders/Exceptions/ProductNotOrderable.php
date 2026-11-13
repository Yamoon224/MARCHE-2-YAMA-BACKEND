<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use DomainException;

final class ProductNotOrderable extends DomainException
{
    public static function forProduct(int $productId): self
    {
        return new self("Le produit {$productId} est introuvable ou indisponible.");
    }
}
