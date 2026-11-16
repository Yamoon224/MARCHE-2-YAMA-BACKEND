<?php

declare(strict_types=1);

namespace App\Modules\Pos\Exceptions;

use App\Shared\Money;
use DomainException;

final class PosNotAllowed extends DomainException
{
    public static function alreadyOpen(): self
    {
        return new self('Votre caisse est déjà ouverte.');
    }

    public static function noOpenSession(): self
    {
        return new self("Ouvrez votre caisse avant d'encaisser.");
    }

    public static function notYours(): self
    {
        return new self('Cette caisse ne vous appartient pas.');
    }

    public static function alreadyClosed(): self
    {
        return new self('Cette caisse est déjà fermée.');
    }

    public static function insufficientTender(int $total): self
    {
        return new self('Le montant reçu est insuffisant : '.Money::eur($total).' à encaisser.');
    }

    public static function varianceNeedsNote(int $expected): self
    {
        return new self('Le montant compté diffère des '.Money::eur($expected).' attendus : expliquez l\'écart.');
    }
}
