<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\PayableOrder;

/** Port : le module Payments lit et fait avancer les commandes sans connaître leurs modèles. */
interface OrderPaymentPort
{
    public function findPayable(int $orderId): ?PayableOrder;

    /** Marque la commande payée ; retourne false si elle ne peut plus l'être (annulée, expirée). */
    public function markPaid(int $orderId): bool;

    /** Passe la commande en « paiement échoué » si elle attendait encore son paiement. */
    public function markPaymentFailed(int $orderId): void;
}
