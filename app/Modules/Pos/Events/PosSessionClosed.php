<?php

declare(strict_types=1);

namespace App\Modules\Pos\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Caisse fermée : espèces attendues dans le tiroir (fonds de caisse + ventes en espèces) et espèces comptées. */
final class PosSessionClosed
{
    use Dispatchable;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $cashierId,
        public readonly int $expectedCashMinor,
        public readonly int $countedMinor,
    ) {}
}
