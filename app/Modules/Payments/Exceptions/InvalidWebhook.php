<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

final class InvalidWebhook extends RuntimeException
{
    public static function malformed(): self
    {
        return new self('Webhook de paiement mal formé.');
    }

    public static function badSignature(): self
    {
        return new self('Signature de webhook invalide.');
    }

    public static function unknownProvider(string $provider): self
    {
        return new self("Fournisseur de paiement inconnu : {$provider}.");
    }
}
