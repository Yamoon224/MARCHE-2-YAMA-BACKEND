<?php

declare(strict_types=1);

namespace App\Modules\Claims\Enums;

enum ClaimStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
