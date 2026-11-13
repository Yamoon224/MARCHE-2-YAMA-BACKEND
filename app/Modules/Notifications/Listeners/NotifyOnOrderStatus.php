<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Templates;
use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use App\Modules\Orders\Events\OrderStatusChanged;
use Throwable;

/** Prévient le client aux étapes qui comptent pour lui. Une panne de notification ne bloque jamais la commande. */
final class NotifyOnOrderStatus
{
    private const TYPES = [
        'confirmed' => Templates::ORDER_CONFIRMED,
        'out_for_delivery' => Templates::DELIVERY_STARTED,
        'delivered' => Templates::ORDER_DELIVERED,
        'delivery_failed' => Templates::DELIVERY_FAILED,
        'cancelled' => Templates::ORDER_CANCELLED,
    ];

    public function __construct(
        private readonly OrderCustomerDirectory $orders,
        private readonly SendNotificationAction $send,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to === $event->from) {
            return;
        }

        try {
            // « Prête » ne concerne le client que pour un retrait en magasin (à domicile, c'est le livreur qui part).
            $type = $event->to->value === 'ready'
                ? ($this->orders->fulfillmentOf($event->orderId) === 'pickup' ? Templates::ORDER_READY_PICKUP : null)
                : (self::TYPES[$event->to->value] ?? null);

            if ($type === null) {
                return;
            }

            $customerId = $this->orders->customersOf([$event->orderId])[$event->orderId] ?? null;

            if ($customerId !== null) {
                $this->send->execute($customerId, $type, ['order_id' => $event->orderId], "order:{$event->orderId}:{$event->to->value}");
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
