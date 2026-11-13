<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum PaymentMethod: string
{
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case CashOnDelivery = 'cash_on_delivery';

    public function isOnline(): bool
    {
        return $this !== self::CashOnDelivery;
    }
}
