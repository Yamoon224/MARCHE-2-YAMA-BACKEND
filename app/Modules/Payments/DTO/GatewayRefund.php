<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTO;

final readonly class GatewayRefund
{
    public function __construct(public string $providerReference) {}
}
