<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Listeners;

use App\Modules\Invoicing\Actions\GenerateCreditNoteAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

/** Commande annulée alors qu'elle était facturée : avoir du solde de la facture. Sans facture, rien à faire. */
final class CreditOnCancellation
{
    public function __construct(private readonly GenerateCreditNoteAction $credit) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            $this->credit->execute(
                $event->orderId, $this->credit->creditable($event->orderId), $event->to === OrderStatus::Returned ? 'Retour de la commande au magasin' : 'Annulation de la commande', "cancellation:order:{$event->orderId}",
            );
        }
    }
}
