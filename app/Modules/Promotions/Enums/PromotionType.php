<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Enums;

enum PromotionType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
