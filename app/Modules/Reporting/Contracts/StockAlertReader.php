<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Contracts;

/** Alertes de stock, fournies par le module Stocks. */
interface StockAlertReader
{
    /** Lots non bloqués, encore en stock, qui expirent d'ici $days jours (ou sont déjà périmés). */
    public function expiringLots(int $days): int;

    /** Produits dont tous les lots disponibles sont épuisés ou bloqués. */
    public function outOfStockProducts(): int;
}
