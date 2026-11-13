<?php

declare(strict_types=1);

namespace App\Modules\Claims\Exceptions;

use DomainException;

final class ClaimNotAllowed extends DomainException
{
    public static function orderNotFound(): self
    {
        return new self('Commande introuvable.');
    }

    public static function notDelivered(): self
    {
        return new self("Seule une commande livrée peut faire l'objet d'une réclamation.");
    }

    public static function windowExpired(int $hours): self
    {
        return new self("Le délai de réclamation ({$hours} h après la livraison) est dépassé.");
    }

    public static function alreadyClaimed(): self
    {
        return new self('Une réclamation existe déjà pour cette commande.');
    }

    public static function unknownLine(): self
    {
        return new self('Un article ne fait pas partie de cette commande.');
    }

    public static function duplicateLine(): self
    {
        return new self('Un même article ne peut être déclaré qu\'une fois.');
    }

    public static function quantityTooHigh(string $name, int $max): self
    {
        return new self("Quantité supérieure à celle reçue pour « {$name} » (maximum {$max}).");
    }

    public static function photoRequired(): self
    {
        return new self('Une photo est obligatoire pour un produit abîmé, périmé ou non conforme.');
    }

    public static function alreadyDecided(): self
    {
        return new self('Cette réclamation a déjà fait l\'objet d\'une décision.');
    }

    public static function amountAboveCeiling(int $ceiling): self
    {
        return new self("Le remboursement ne peut pas dépasser la valeur des articles réclamés ({$ceiling}).");
    }

    public static function refundImpossible(string $why): self
    {
        return new self("Remboursement impossible : {$why}");
    }
}
