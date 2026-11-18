<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Contracts;

/** Catégorie des produits, fournie par le module Catalogue. */
interface ProductCategoryReader
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, array{id: int, name: string}> catégorie par identifiant de produit
     */
    public function categoriesOf(array $productIds): array;
}
