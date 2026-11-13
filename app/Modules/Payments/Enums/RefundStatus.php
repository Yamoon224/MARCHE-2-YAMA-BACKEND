<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
