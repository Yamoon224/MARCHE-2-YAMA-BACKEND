<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Exceptions;

use DomainException;

final class DeliveryNotAllowed extends DomainException
{
    public static function notACourier(): self
    {
        return new self("Ce compte n'est pas un livreur.");
    }

    public static function wrongState(string $action): self
    {
        return new self("Action impossible dans l'état actuel de la livraison ({$action}).");
    }

    public static function codeLocked(): self
    {
        return new self('Trop de codes erronés : contactez la régulation pour débloquer la livraison.');
    }

    public static function wrongCode(int $remaining): self
    {
        return new self("Code de confirmation incorrect ({$remaining} essai(s) restant(s)).");
    }

    public static function cashMismatch(int $due): self
    {
        return new self("Montant encaissé incorrect : {$due} F CFA attendus.");
    }

    public static function orderNotDeliverable(): self
    {
        return new self("Cette commande n'est pas une livraison à domicile prête à partir.");
    }
}
