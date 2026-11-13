<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTO;

final readonly class GatewayCharge
{
    public function __construct(
        public string $providerReference,
        public ?string $actionUrl = null,
    ) {}
}
