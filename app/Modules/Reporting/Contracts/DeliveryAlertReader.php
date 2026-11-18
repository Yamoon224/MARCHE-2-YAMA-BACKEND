<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Contracts;

/** Alertes de livraison, fournies par le module Livraison. */
interface DeliveryAlertReader
{
    /** Livraisons non terminées dont le créneau réservé est dépassé. */
    public function lateDeliveries(): int;
}
