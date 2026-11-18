<?php

declare(strict_types=1);

namespace App\Modules\Claims\Services;

use App\Modules\Claims\Enums\ClaimStatus;
use App\Modules\Claims\Models\Claim;
use App\Modules\Reporting\Contracts\ClaimAlertReader;

final class EloquentClaimAlertReader implements ClaimAlertReader
{
    public function openClaims(): int
    {
        return Claim::query()->where('status', ClaimStatus::Open->value)->count();
    }
}
