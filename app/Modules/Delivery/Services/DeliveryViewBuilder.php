<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Orders\Contracts\OrderDeliveryPort;
use App\Modules\Payments\Contracts\CashDueReader;

/** Assemble la vue d'une livraison pour le personnel et le livreur (adresse, téléphone, créneau, argent à encaisser). */
final class DeliveryViewBuilder
{
    public function __construct(
        private readonly OrderDeliveryPort $orders,
        private readonly CashDueReader $cash,
        private readonly CourierDirectory $couriers,
    ) {}

    /** @return array<string, mixed> */
    public function build(Delivery $delivery): array
    {
        $order = $this->orders->find($delivery->order_id);
        $slot = $order?->slotId !== null ? DeliverySlot::find($order->slotId) : null;

        return [
            'id' => $delivery->id,
            'order_id' => $delivery->order_id,
            'status' => $delivery->status->value,
            'courier_id' => $delivery->courier_id,
            'courier_name' => $delivery->courier_id !== null ? $this->couriers->nameOf($delivery->courier_id) : null,
            'phone' => $order?->phone,
            'address' => $order?->address,
            'slot_starts_at' => $slot?->starts_at->toIso8601String(),
            'slot_ends_at' => $slot?->ends_at->toIso8601String(),
            'cash_due_minor' => $this->cash->cashDue($delivery->order_id),
            'failure_reason' => $delivery->failure_reason,
            'code_locked' => $delivery->isCodeLocked(),
        ];
    }
}
