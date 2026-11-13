<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

use Carbon\CarbonImmutable;

final readonly class ClaimableOrder
{
    /** @param list<ClaimableLine> $lines */
    public function __construct(
        public int $orderId,
        public ?int $userId,
        public string $status,
        public ?CarbonImmutable $deliveredAt,
        public array $lines,
    ) {}
}
