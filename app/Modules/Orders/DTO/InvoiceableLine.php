<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

final readonly class InvoiceableLine
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public string $saleUnit,
        public int $quantity,
        public int $unitPriceMinor,
        public int $lineTotalMinor,
        public int $vatRateBp,
    ) {}
}
