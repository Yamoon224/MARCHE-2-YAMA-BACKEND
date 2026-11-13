<?php

declare(strict_types=1);

namespace App\Modules\Payments\Listeners;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Enums\RefundKind;

/** Commande annulée après paiement : tout ce qui a été encaissé est remboursé. Sans paiement encaissé, rien à faire. */
final class RefundOnCancellation
{
    public function __construct(private readonly RefundPaymentAction $refund) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return;
        }

        $this->refund->execute(
            $event->orderId, $this->refund->refundable($event->orderId), RefundKind::Cancellation,
            $event->to === OrderStatus::Returned ? 'Retour de la commande au magasin' : 'Annulation de la commande', "cancellation:order:{$event->orderId}", strict: false,
        );
    }
}
