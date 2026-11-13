<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Enums;

enum DeliveryStatus: string
{
    /** Commande prête, pas encore confiée à un livreur. */
    case Pending = 'pending';
    case Assigned = 'assigned';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    public function isActive(): bool
    {
        return in_array($this, [self::Assigned, self::OutForDelivery, self::Failed], true);
    }
}
