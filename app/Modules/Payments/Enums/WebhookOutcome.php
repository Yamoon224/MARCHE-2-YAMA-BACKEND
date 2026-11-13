<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum WebhookOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Flagged = 'flagged';
    case Ignored = 'ignored';
    case Duplicate = 'duplicate';
}
