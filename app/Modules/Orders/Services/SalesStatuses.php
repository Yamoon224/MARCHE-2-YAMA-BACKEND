<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\OrderStatus;

/** Statuts des commandes qui comptent comme une vente (payées et non annulées, remboursées ou retournées). */
final class SalesStatuses
{
    /** @return list<OrderStatus> */
    public static function counted(): array
    {
        return [
            OrderStatus::Paid, OrderStatus::UnderReview, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready,
            OrderStatus::Assigned, OrderStatus::OutForDelivery, OrderStatus::DeliveryFailed, OrderStatus::Delivered,
            OrderStatus::Completed, OrderStatus::Claim,
        ];
    }
}
