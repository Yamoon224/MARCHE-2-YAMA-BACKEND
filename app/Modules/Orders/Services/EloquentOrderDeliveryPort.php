<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Orders\DTO\DeliverableOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

final class EloquentOrderDeliveryPort implements OrderDeliveryPort
{
    public function __construct(private readonly TransitionOrderAction $transition) {}

    public function find(int $orderId): ?DeliverableOrder
    {
        $order = Order::find($orderId);

        if ($order === null) {
            return null;
        }

        return new DeliverableOrder(
            orderId: $order->id, userId: $order->user_id, fulfillment: $order->fulfillment, status: $order->status->value,
            address: $order->delivery_address, phone: $order->contact_phone, slotId: $order->delivery_slot_id,
            finalTotalMinor: $order->final_total_minor ?? $order->total_minor, currency: $order->currency,
        );
    }

    public function markAssigned(int $orderId, ?int $userId): void
    {
        $this->move($orderId, OrderStatus::Assigned, $userId);
    }

    public function markOutForDelivery(int $orderId, ?int $userId): void
    {
        $this->move($orderId, OrderStatus::OutForDelivery, $userId);
    }

    public function markDelivered(int $orderId, ?int $userId): void
    {
        $this->move($orderId, OrderStatus::Delivered, $userId);
    }

    public function markDeliveryFailed(int $orderId, ?int $userId, string $reason): void
    {
        $this->move($orderId, OrderStatus::DeliveryFailed, $userId, $reason);
    }

    public function markReturned(int $orderId, ?int $userId, string $reason): void
    {
        $this->move($orderId, OrderStatus::Returned, $userId, $reason);
    }

    private function move(int $orderId, OrderStatus $to, ?int $userId, ?string $reason = null): void
    {
        $this->transition->execute(Order::findOrFail($orderId), $to, $userId, $reason);
    }
}
