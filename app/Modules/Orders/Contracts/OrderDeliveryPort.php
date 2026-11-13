<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\DeliverableOrder;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;

/**
 * Port : le module Delivery lit les commandes à livrer et fait avancer leur statut sans connaître leurs modèles.
 * Les passages « livraison » d'une commande à domicile ne se font QUE par ce port (le code de confirmation
 * et l'encaissement ne se contournent pas par l'API générale des commandes).
 */
interface OrderDeliveryPort
{
    public function find(int $orderId): ?DeliverableOrder;

    /** @throws InvalidOrderTransition */
    public function markAssigned(int $orderId, ?int $userId): void;

    /** @throws InvalidOrderTransition */
    public function markOutForDelivery(int $orderId, ?int $userId): void;

    /** @throws InvalidOrderTransition */
    public function markDelivered(int $orderId, ?int $userId): void;

    /** @throws InvalidOrderTransition */
    public function markDeliveryFailed(int $orderId, ?int $userId, string $reason): void;

    /** @throws InvalidOrderTransition */
    public function markReturned(int $orderId, ?int $userId, string $reason): void;
}
