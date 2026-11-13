<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

/** Choix de livraison du client : zone, créneau, adresse (repères, géolocalisation) et téléphone du destinataire. */
final readonly class DeliveryChoice
{
    /** @param array<string, mixed> $address */
    public function __construct(
        public int $zoneId,
        public int $slotId,
        public array $address,
        public string $phone,
    ) {}
}
