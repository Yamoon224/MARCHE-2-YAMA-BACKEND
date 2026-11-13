<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

/** API publique de Payments : combien le livreur doit encaisser auprès du client à la remise de la commande. */
interface CashDueReader
{
    /** 0 si la commande est déjà payée en ligne ; sinon le montant réel (après préparation) d'un paiement à la livraison. */
    public function cashDue(int $orderId): int;
}
