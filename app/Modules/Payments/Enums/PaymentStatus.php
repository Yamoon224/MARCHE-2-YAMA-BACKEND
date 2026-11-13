<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case PendingOnDelivery = 'pending_on_delivery';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    /** Montant ou devise différents de ceux attendus : à traiter par la finance. */
    case Flagged = 'flagged';
}
