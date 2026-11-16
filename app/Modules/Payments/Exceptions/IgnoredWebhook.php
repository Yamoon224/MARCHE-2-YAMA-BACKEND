<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** Événement authentique mais sans effet pour nous (type d'événement non géré) : on répond 200 pour qu'il ne soit pas renvoyé. */
final class IgnoredWebhook extends RuntimeException
{
    public static function unhandledType(string $type): self
    {
        return new self("Type d'événement non géré : {$type}.");
    }
}
