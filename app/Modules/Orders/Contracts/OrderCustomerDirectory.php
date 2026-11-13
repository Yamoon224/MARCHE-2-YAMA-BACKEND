<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

/** API publique d'Orders : retrouver à quels clients appartiennent des commandes (traçabilité des lots). */
interface OrderCustomerDirectory
{
    /**
     * @param  list<int>  $orderIds
     * @return array<int, int|null> identifiant de commande => identifiant du client (null : commande sans compte)
     */
    public function customersOf(array $orderIds): array;

    /** @return 'pickup'|'delivery'|null null : commande inconnue */
    public function fulfillmentOf(int $orderId): ?string;
}
