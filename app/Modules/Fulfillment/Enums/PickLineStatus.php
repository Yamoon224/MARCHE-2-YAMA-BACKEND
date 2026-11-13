<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Enums;

enum PickLineStatus: string
{
    case Pending = 'pending';
    case Picked = 'picked';
    /** Quantité servie inférieure à la commande, ou article en rupture (quantité 0). */
    case Short = 'short';
    case Substituted = 'substituted';
}
