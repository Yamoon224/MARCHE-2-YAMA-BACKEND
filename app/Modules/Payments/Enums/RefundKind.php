<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum RefundKind: string
{
    /** Écart de préparation (pesée plus légère, rupture, remplaçant moins cher). */
    case Adjustment = 'adjustment';
    /** Commande annulée après paiement. */
    case Cancellation = 'cancellation';
    /** Geste commercial ou réclamation décidée par le personnel. */
    case Manual = 'manual';
    /** Réclamation acceptée (produit manquant, abîmé, périmé, non conforme). */
    case Claim = 'claim';
    /** Paiement reçu pour une commande qui n'était plus payable. */
    case LatePayment = 'late_payment';
}
