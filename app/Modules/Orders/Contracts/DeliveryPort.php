<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\DeliveryQuote;
use App\Modules\Orders\Exceptions\DeliveryUnavailable;

/** Port : Orders demande frais et créneau au module Delivery sans connaître ses modèles. */
interface DeliveryPort
{
    /** @throws DeliveryUnavailable zone inactive ou montant minimum non atteint */
    public function quote(int $zoneId, int $subtotalMinor): DeliveryQuote;

    /** Réserve une place du créneau pour la commande (atomique). @throws DeliveryUnavailable créneau complet, passé ou d'une autre zone */
    public function bookSlot(int $slotId, int $zoneId, int $orderId): void;
}
