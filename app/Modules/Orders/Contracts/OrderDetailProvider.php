<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

/**
 * Port : un module (paiements, livraison…) ajoute sa section au détail d'une commande vu par le personnel,
 * sans qu'Orders connaisse ses modèles. Les fournisseurs se déclarent avec le tag « order.details ».
 */
interface OrderDetailProvider
{
    /** Clé de la section dans `details` (ex. « payments »). */
    public function key(): string;

    /** @return array<mixed>|null null : rien à montrer pour cette commande */
    public function detailsFor(int $orderId): ?array;
}
