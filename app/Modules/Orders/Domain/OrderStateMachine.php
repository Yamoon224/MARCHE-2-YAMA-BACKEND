<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;

/** Règles pures de transition : aucune dépendance base de données. */
final class OrderStateMachine
{
    public function assertCanTransition(OrderStatus $from, OrderStatus $to, ?string $reason): void
    {
        if (! $from->canTransitionTo($to)) {
            throw InvalidOrderTransition::notAllowed($from, $to);
        }

        if ($to->requiresReason() && ($reason === null || trim($reason) === '')) {
            throw InvalidOrderTransition::reasonRequired($to);
        }
    }
}
