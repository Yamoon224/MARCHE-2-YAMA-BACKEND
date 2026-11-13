<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Un lot vient d'être rappelé (alerte sanitaire) ; porte les clients qui l'ont déjà reçu, à prévenir. */
final class LotRecalled
{
    use Dispatchable;

    /** @param  list<array{customer_id: int, order_id: int}>  $deliveredTo  un client par commande livrée contenant le lot */
    public function __construct(
        public readonly int $lotId,
        public readonly string $lotCode,
        public readonly string $reason,
        public readonly array $deliveredTo,
    ) {}
}
