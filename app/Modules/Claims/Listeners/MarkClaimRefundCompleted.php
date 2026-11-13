<?php

declare(strict_types=1);

namespace App\Modules\Claims\Listeners;

use App\Modules\Claims\Models\Claim;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Events\RefundIssued;

/** Un remboursement de réclamation refusé puis repris avec succès (reprise automatique) en avertit la réclamation. */
final class MarkClaimRefundCompleted
{
    public function handle(RefundIssued $event): void
    {
        if ($event->kind === RefundKind::Claim) {
            Claim::where('order_id', $event->orderId)->update(['refund_completed' => true]);
        }
    }
}
