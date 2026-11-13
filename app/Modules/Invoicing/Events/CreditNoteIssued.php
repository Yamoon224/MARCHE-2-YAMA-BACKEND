<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Déclenche l'écriture comptable d'annulation (partielle ou totale) de la vente. */
final class CreditNoteIssued
{
    use Dispatchable;

    public function __construct(
        public readonly int $creditNoteId,
        public readonly int $orderId,
        public readonly string $number,
        public readonly int $totalMinor,
        public readonly int $netMinor,
        public readonly int $vatMinor,
        public readonly string $currency,
    ) {}
}
