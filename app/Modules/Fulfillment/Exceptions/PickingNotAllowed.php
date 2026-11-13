<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Exceptions;

use DomainException;

final class PickingNotAllowed extends DomainException
{
    public static function notInProgress(): self
    {
        return new self('La préparation n\'est pas en cours : démarrez-la d\'abord.');
    }

    public static function alreadyTaken(): self
    {
        return new self('Cette préparation est déjà prise en charge par un autre préparateur.');
    }

    public static function quantityAbove(int $max): self
    {
        return new self("Quantité trop élevée : maximum servable {$max}.");
    }

    public static function substitutionRefused(): self
    {
        return new self('Le client a refusé tout remplacement pour cet article.');
    }

    public static function substituteUnavailable(): self
    {
        return new self('Produit de remplacement introuvable ou indisponible.');
    }

    public static function substituteUnitMismatch(): self
    {
        return new self('Le remplaçant doit se vendre dans la même unité que l\'article commandé.');
    }

    public static function linesPending(int $count): self
    {
        return new self("{$count} article(s) restent à traiter avant de terminer la préparation.");
    }
}
