<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use App\Modules\Orders\Models\Order;

final class EloquentOrderCustomerDirectory implements OrderCustomerDirectory
{
    public function customersOf(array $orderIds): array
    {
        // Une vente en caisse n'a pas de client à notifier : son compte rattaché est celui du caissier.
        return Order::whereIn('id', $orderIds)->get(['id', 'user_id', 'channel'])
            ->mapWithKeys(fn (Order $o) => [$o->id => $o->channel === 'pos' ? null : $o->user_id])->all();
    }

    public function fulfillmentOf(int $orderId): ?string
    {
        $value = Order::whereKey($orderId)->value('fulfillment');

        return $value === 'delivery' || $value === 'pickup' ? $value : null;
    }
}
