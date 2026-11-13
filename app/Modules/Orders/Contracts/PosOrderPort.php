<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\PosOrder;
use App\Modules\Orders\Exceptions\InsufficientStock;
use App\Modules\Orders\Exceptions\ProductNotOrderable;

/** Port : la caisse crée et clôt des commandes sans connaître leurs modèles. */
interface PosOrderPort
{
    /**
     * Crée la commande (canal « caisse ») et réserve le stock, comme pour une commande en ligne.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     *
     * @throws ProductNotOrderable
     * @throws InsufficientStock
     */
    public function place(int $cashierId, array $items): PosOrder;

    /** Commande payée au comptoir : confirmée, préparée et remise au client sur-le-champ (le stock sort). */
    public function completeInStore(int $orderId, int $cashierId): void;
}
