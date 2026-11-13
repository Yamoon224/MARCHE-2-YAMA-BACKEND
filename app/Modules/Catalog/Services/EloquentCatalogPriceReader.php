<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Contracts\CatalogPriceReader;
use App\Modules\Orders\DTO\PricedProduct;

final class EloquentCatalogPriceReader implements CatalogPriceReader
{
    public function findOrderable(array $productIds): array
    {
        $priced = [];

        foreach (Product::query()->whereIn('id', $productIds)->where('is_active', true)->get() as $product) {
            $priced[$product->id] = new PricedProduct(
                productId: $product->id,
                sku: $product->sku,
                name: $product->name,
                saleUnit: $product->sale_unit->value,
                priceMinor: $product->price_minor,
                vatRateBp: $product->vat_rate_bp,
            );
        }

        return $priced;
    }
}
