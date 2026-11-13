<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Actions\GenerateCreditNoteAction;
use App\Modules\Orders\Events\OrderTotalAdjusted;

/** La facture a été émise au paiement ; un écart à la baisse après préparation se corrige par un avoir. */
final class CreditOnPreparationAdjustment
{
    public function __construct(private readonly GenerateCreditNoteAction $credit) {}

    public function handle(OrderTotalAdjusted $event): void
    {
        if ($event->deltaMinor < 0) {
            $this->credit->execute($event->orderId, -$event->deltaMinor, 'Écart de préparation', "adjustment:order:{$event->orderId}");
        }
    }
}
