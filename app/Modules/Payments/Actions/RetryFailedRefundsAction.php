<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Refund;

final class RetryFailedRefundsAction
{
    public function __construct(private readonly RefundPaymentAction $refunds) {}

    /** Relance les remboursements en échec ou restés en attente ; retourne le nombre de ceux qui ont abouti. */
    public function execute(): int
    {
        $succeeded = 0;

        foreach (Refund::whereIn('status', [RefundStatus::Failed, RefundStatus::Pending])->orderBy('id')->get() as $refund) {
            if ($this->refunds->settle($refund)->status === RefundStatus::Succeeded) {
                $succeeded++;
            }
        }

        return $succeeded;
    }
}
