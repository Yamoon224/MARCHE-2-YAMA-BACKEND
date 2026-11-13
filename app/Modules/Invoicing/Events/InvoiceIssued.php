<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Déclenche la constatation du chiffre d'affaires et de la TVA en comptabilité. */
final class InvoiceIssued
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $orderId,
        public readonly string $number,
        public readonly int $totalMinor,
        public readonly int $netMinor,
        public readonly int $vatMinor,
        public readonly string $currency,
    ) {}
}
