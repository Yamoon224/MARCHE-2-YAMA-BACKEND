<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Enums;

enum PickListStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
