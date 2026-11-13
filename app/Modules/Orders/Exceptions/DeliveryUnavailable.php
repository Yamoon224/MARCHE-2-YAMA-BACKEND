<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use DomainException;

final class DeliveryUnavailable extends DomainException
{
    public static function zoneUnavailable(): self
    {
        return new self('Cette zone n\'est pas desservie.');
    }

    public static function minimumOrder(int $minimumMinor): self
    {
        return new self("Le montant minimum de commande pour cette zone est de {$minimumMinor}.");
    }

    public static function slotUnavailable(): self
    {
        return new self('Ce créneau n\'est plus disponible.');
    }
}
