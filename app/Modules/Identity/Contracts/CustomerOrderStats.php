<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** Statistiques de commandes par client, fournies par le module Commandes. */
interface CustomerOrderStats
{
    /**
     * @param  list<int>  $customerIds
     * @return array<int, array{orders_count: int, total_spent_minor: int}> indexé par identifiant client
     */
    public function statsFor(array $customerIds): array;
}
