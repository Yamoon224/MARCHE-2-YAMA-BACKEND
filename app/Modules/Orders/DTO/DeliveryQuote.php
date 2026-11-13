<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTO;

final readonly class DeliveryQuote
{
    public function __construct(public int $feeMinor) {}
}
