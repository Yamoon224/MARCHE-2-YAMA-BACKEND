<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Contracts\RefundIssuer;
use App\Modules\Payments\DTO\RefundOutcome;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Enums\RefundStatus;

final class PaymentsRefundIssuer implements RefundIssuer
{
    public function __construct(private readonly RefundPaymentAction $refunds) {}

    public function refundable(int $orderId): int
    {
        return $this->refunds->refundable($orderId);
    }

    public function forClaim(int $orderId, int $amountMinor, string $reason, string $sourceKey): RefundOutcome
    {
        $refunds = $this->refunds->execute($orderId, $amountMinor, RefundKind::Claim, $reason, $sourceKey);

        return new RefundOutcome(
            $amountMinor,
            collect($refunds)->every(fn ($r) => $r->status === RefundStatus::Succeeded),
        );
    }
}
