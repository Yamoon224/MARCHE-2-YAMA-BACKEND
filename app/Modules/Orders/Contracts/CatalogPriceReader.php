<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\PricedProduct;

/** Port : Orders lit les prix officiels du catalogue sans connaître ses modèles. */
interface CatalogPriceReader
{
    /**
     * Produits actifs uniquement, indexés par identifiant.
     *
     * @param  list<int>  $productIds
     * @return array<int, PricedProduct>
     */
    public function findOrderable(array $productIds): array;
}
