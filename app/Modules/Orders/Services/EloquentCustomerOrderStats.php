<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Identity\Contracts\CustomerOrderStats;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

final class EloquentCustomerOrderStats implements CustomerOrderStats
{
    public function statsFor(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $stats = [];
        $rows = Order::query()
            ->selectRaw('user_id, COUNT(*) as orders_count, SUM(COALESCE(final_total_minor, total_minor)) as total_spent_minor')
            ->whereIn('user_id', $customerIds)
            ->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, SalesStatuses::counted()))
            ->groupBy('user_id')
            ->get();

        foreach ($rows as $row) {
            $stats[(int) $row->getAttribute('user_id')] = [
                'orders_count' => (int) $row->getAttribute('orders_count'),
                'total_spent_minor' => (int) $row->getAttribute('total_spent_minor'),
            ];
        }

        return $stats;
    }
}
