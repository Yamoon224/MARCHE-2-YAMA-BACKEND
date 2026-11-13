<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

final readonly class PricedProduct
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public string $saleUnit,
        public int $priceMinor,
        public int $vatRateBp = 0,
    ) {}
}
