<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** L'agrégateur a refusé ou n'a pas pu exécuter l'opération : elle pourra être reprise. */
final class GatewayFailure extends RuntimeException
{
    public static function refundFailed(string $why): self
    {
        return new self("Remboursement refusé par l'agrégateur : {$why}");
    }
}
