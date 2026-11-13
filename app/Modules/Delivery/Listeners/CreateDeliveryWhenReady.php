<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Listeners;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Database\UniqueConstraintViolationException;

/** Une commande à domicile prête reçoit sa livraison et son code de confirmation (6 chiffres, remis au client). */
final class CreateDeliveryWhenReady
{
    public function __construct(private readonly OrderDeliveryPort $orders) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Ready || Delivery::where('order_id', $event->orderId)->exists()) {
            return;
        }

        $order = $this->orders->find($event->orderId);
        if ($order === null || $order->fulfillment !== 'delivery') {
            return;
        }

        try {
            Delivery::create([
                'order_id' => $event->orderId,
                'status' => DeliveryStatus::Pending,
                'confirmation_code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Créée en parallèle par un autre processus : rien à faire.
        }
    }
}
