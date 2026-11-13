<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\OrderClaimPort;
use App\Modules\Orders\DTO\ClaimableLine;
use App\Modules\Orders\DTO\ClaimableOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

final class EloquentOrderClaimPort implements OrderClaimPort
{
    public function __construct(private readonly TransitionOrderAction $transition) {}

    public function find(int $orderId): ?ClaimableOrder
    {
        $order = Order::with(['lines', 'history'])->find($orderId);

        if ($order === null) {
            return null;
        }

        $delivered = $order->history->where('to_status', OrderStatus::Delivered->value)->last();

        return new ClaimableOrder(
            orderId: $order->id,
            userId: $order->user_id,
            status: $order->status->value,
            deliveredAt: $delivered?->created_at !== null ? CarbonImmutable::instance($delivered->created_at) : null,
            lines: $order->lines->map(fn ($l) => new ClaimableLine(
                orderLineId: $l->id,
                productId: $l->final_product_id ?? $l->product_id,
                name: $l->final_name ?? $l->name,
                saleUnit: $l->sale_unit,
                // Ce que le client a réellement reçu et payé : quantité et montant après préparation.
                quantity: $l->final_quantity ?? $l->quantity,
                lineTotalMinor: $l->final_line_total_minor ?? $l->line_total_minor,
            ))->all(),
        );
    }

    public function markClaimOpened(int $orderId, int $userId, string $reason): void
    {
        $this->transition->execute(Order::findOrFail($orderId), OrderStatus::Claim, $userId, $reason);
    }

    public function markClaimResolved(int $orderId, int $staffId, bool $refunded): void
    {
        $this->transition->execute(Order::findOrFail($orderId), $refunded ? OrderStatus::Refunded : OrderStatus::Completed, $staffId);
    }
}
