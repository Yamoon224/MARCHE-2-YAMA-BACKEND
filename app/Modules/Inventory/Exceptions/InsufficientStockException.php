<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public static function forProduct(int $productId, int $requested, int $available): self
    {
        return new self("Stock insuffisant pour le produit {$productId} : {$requested} demandé, {$available} disponible.");
    }
}
