<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use DomainException;

final class AccountClaimNotAllowed extends DomainException
{
    public static function notAGuest(): self
    {
        return new self('Vous avez déjà un compte.');
    }

    public static function emailTaken(): self
    {
        return new self('Un compte existe déjà avec cet e-mail : connectez-vous pour retrouver vos commandes.');
    }
}
