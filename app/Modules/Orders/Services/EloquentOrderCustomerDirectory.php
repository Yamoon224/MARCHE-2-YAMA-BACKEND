<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use App\Modules\Orders\Models\Order;

final class EloquentOrderCustomerDirectory implements OrderCustomerDirectory
{
    public function customersOf(array $orderIds): array
    {
        return Order::whereIn('id', $orderIds)->pluck('user_id', 'id')->all();
    }
}
