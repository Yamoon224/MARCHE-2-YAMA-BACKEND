<?php

declare(strict_types=1);

namespace App\Modules\Claims\Enums;

/** À qui imputer le coût d'une réclamation acceptée (statistiques de qualité). */
enum CostBearer: string
{
    case Supplier = 'supplier';
    case Preparation = 'preparation';
    case Delivery = 'delivery';
    case Store = 'store';
}
