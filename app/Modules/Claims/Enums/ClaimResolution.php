<?php

declare(strict_types=1);

namespace App\Modules\Claims\Enums;

enum ClaimResolution: string
{
    /** Argent rendu au client sur le moyen de paiement d'origine (avoir émis sur la facture). */
    case Refund = 'refund';
    /** Nouvel envoi des articles concernés, sans mouvement d'argent. */
    case Redelivery = 'redelivery';
}
