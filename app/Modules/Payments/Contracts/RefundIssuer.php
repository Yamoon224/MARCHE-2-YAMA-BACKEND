<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\DTO\RefundOutcome;
use App\Modules\Payments\Exceptions\RefundNotAllowed;

/** API publique de Payments : rembourser un client à la suite d'une réclamation acceptée. */
interface RefundIssuer
{
    /** Montant encore remboursable pour la commande (encaissé - déjà remboursé). */
    public function refundable(int $orderId): int;

    /**
     * Idempotent par `$sourceKey`.
     *
     * @throws RefundNotAllowed rien d'encaissé, ou montant supérieur au remboursable
     */
    public function forClaim(int $orderId, int $amountMinor, string $reason, string $sourceKey): RefundOutcome;
}
