<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use LogicException;

/** Interdit toute modification ou suppression d'un enregistrement une fois créé (documents légaux, grand livre). */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' est immuable : toute correction passe par un document inverse.'));
        static::deleting(fn () => throw new LogicException(static::class.' est immuable : suppression interdite.'));
    }
}
