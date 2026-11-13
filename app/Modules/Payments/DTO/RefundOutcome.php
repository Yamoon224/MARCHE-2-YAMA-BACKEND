<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTO;

final readonly class RefundOutcome
{
    public function __construct(
        public int $requestedMinor,
        /** Faux si l'agrégateur a refusé : le remboursement est conservé et repris automatiquement. */
        public bool $completed,
    ) {}
}
