<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use App\Modules\Orders\Enums\OrderStatus;
use DomainException;

final class InvalidOrderTransition extends DomainException
{
    public static function notAllowed(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Transition interdite : {$from->value} -> {$to->value}.");
    }

    public static function reasonRequired(OrderStatus $to): self
    {
        return new self("Un motif est obligatoire pour passer à l'état {$to->value}.");
    }
}
