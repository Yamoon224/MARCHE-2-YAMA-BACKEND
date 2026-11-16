<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'CMD-'.fake()->unique()->numerify('########'),
            'user_id' => null,
            'status' => OrderStatus::AwaitingPayment,
            'currency' => 'EUR',
            'subtotal_minor' => 5000,
            'delivery_fee_minor' => 0,
            'discount_minor' => 0,
            'total_minor' => 5000,
            'channel' => 'web',
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(['user_id' => $userId]);
    }
}
