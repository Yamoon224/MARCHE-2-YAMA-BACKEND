<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum PaymentMethod: string
{
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case CashOnDelivery = 'cash_on_delivery';
    /** Espèces encaissées en caisse (vente au comptoir) : jamais proposé en ligne. */
    case Cash = 'cash';

    public function isOnline(): bool
    {
        return ! in_array($this, [self::CashOnDelivery, self::Cash], true);
    }
}
