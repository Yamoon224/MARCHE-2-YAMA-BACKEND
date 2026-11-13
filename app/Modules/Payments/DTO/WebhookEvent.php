<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTO;

final readonly class WebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $providerReference,
        public bool $succeeded,
        public int $amountMinor,
        public string $currency,
    ) {}
}
