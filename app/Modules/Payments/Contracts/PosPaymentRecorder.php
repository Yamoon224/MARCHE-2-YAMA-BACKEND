<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Exceptions\PaymentNotAllowed;

/** API publique de Payments : constater un paiement reçu au comptoir (espèces, mobile money ou carte contrôlés sur place). */
interface PosPaymentRecorder
{
    /**
     * @param  'cash'|'mobile_money'|'card'  $method
     * @return int identifiant du paiement
     *
     * @throws PaymentNotAllowed
     */
    public function record(int $orderId, string $method, int $amountMinor): int;
}
