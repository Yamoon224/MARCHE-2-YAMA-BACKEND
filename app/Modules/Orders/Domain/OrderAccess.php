<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

/** Qui peut voir une commande et qui peut la faire passer à quel statut. */
final class OrderAccess
{
    public function canView(PermissionHolder $user, Order $order): bool
    {
        return $this->owns($user, $order) || $user->hasPermission('orders.view') || $user->hasPermission('orders.manage');
    }

    public function canTransition(PermissionHolder $user, Order $order, OrderStatus $to): bool
    {
        if ($user->hasPermission('orders.manage')) {
            return true;
        }

        return match ($to) {
            OrderStatus::Cancelled, OrderStatus::Claim => $this->owns($user, $order),
            OrderStatus::Preparing, OrderStatus::Ready => $user->hasPermission('orders.prepare'),
            OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::DeliveryFailed => $user->hasPermission('orders.deliver'),
            default => false,
        };
    }

    private function owns(PermissionHolder $user, Order $order): bool
    {
        return $order->user_id !== null && $order->user_id === $user->getAuthIdentifier();
    }
}
