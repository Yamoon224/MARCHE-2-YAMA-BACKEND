<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'reference' => 'PAY-'.Str::upper((string) Str::ulid()),
            'order_id' => 1,
            'provider' => 'fake',
            'provider_reference' => 'fake_'.Str::lower((string) Str::ulid()),
            'method' => PaymentMethod::MobileMoney,
            'status' => PaymentStatus::Pending,
            'amount_minor' => 5000,
            'currency' => 'EUR',
        ];
    }
}
