<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Orders\DTO\PayableOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;

final class EloquentOrderPaymentPort implements OrderPaymentPort
{
    public function __construct(private readonly TransitionOrderAction $transition) {}

    public function findPayable(int $orderId): ?PayableOrder
    {
        $order = Order::find($orderId);

        if ($order === null) {
            return null;
        }

        return new PayableOrder(
            orderId: $order->id,
            userId: $order->user_id,
            totalMinor: $order->total_minor,
            currency: $order->currency,
            status: $order->status->value,
            acceptsPayment: in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::PaymentFailed], true),
            finalTotalMinor: $order->final_total_minor ?? $order->total_minor,
        );
    }

    public function markPaid(int $orderId): bool
    {
        $order = Order::find($orderId);

        if ($order === null || $order->status === OrderStatus::Cancelled) {
            return false;
        }

        try {
            if ($order->status === OrderStatus::PaymentFailed) {
                $order = $this->transition->execute($order, OrderStatus::AwaitingPayment, null);
            }

            if ($order->status === OrderStatus::AwaitingPayment) {
                $this->transition->execute($order, OrderStatus::Paid, null);
            }
        } catch (InvalidOrderTransition) {
            return false;
        }

        // Déjà payée ou plus avancée : rien à faire, le paiement est bien pris en compte.
        return true;
    }

    public function markPaymentFailed(int $orderId): void
    {
        $order = Order::find($orderId);

        if ($order === null || $order->status !== OrderStatus::AwaitingPayment) {
            return;
        }

        try {
            $this->transition->execute($order, OrderStatus::PaymentFailed, null);
        } catch (InvalidOrderTransition) {
            // Concurrence : le statut a changé entre-temps.
        }
    }
}
