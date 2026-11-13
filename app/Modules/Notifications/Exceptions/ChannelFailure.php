<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use RuntimeException;

final class ChannelFailure extends RuntimeException
{
    public static function unavailable(string $medium): self
    {
        return new self("Aucun fournisseur {$medium} n'est configuré pour cet environnement.");
    }

    public static function providerDown(string $medium): self
    {
        return new self("Le fournisseur {$medium} est indisponible.");
    }
}
