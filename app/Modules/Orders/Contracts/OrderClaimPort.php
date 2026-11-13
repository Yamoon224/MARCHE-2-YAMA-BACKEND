<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\ClaimableOrder;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;

/** Port : le module Claims lit la commande livrée et la fait passer en « réclamation » puis la résout. */
interface OrderClaimPort
{
    public function find(int $orderId): ?ClaimableOrder;

    /** @throws InvalidOrderTransition */
    public function markClaimOpened(int $orderId, int $userId, string $reason): void;

    /**
     * Clôt la réclamation : « remboursée » si un remboursement a été accordé, sinon « terminée ».
     *
     * @throws InvalidOrderTransition
     */
    public function markClaimResolved(int $orderId, int $staffId, bool $refunded): void;
}
