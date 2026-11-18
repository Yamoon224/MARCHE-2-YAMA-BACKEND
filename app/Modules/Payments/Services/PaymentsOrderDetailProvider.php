<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Contracts\OrderDetailProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;

final class PaymentsOrderDetailProvider implements OrderDetailProvider
{
    public function key(): string
    {
        return 'payments';
    }

    public function detailsFor(int $orderId): ?array
    {
        $refunds = Refund::where('order_id', $orderId)->orderBy('id')->get();

        return Payment::where('order_id', $orderId)->orderBy('id')->get()->map(fn (Payment $p) => [
            'reference' => $p->reference,
            'method' => $p->method->value,
            'status' => $p->status->value,
            'amount_minor' => $p->amount_minor,
            'currency' => $p->currency,
            'needs_refund' => $p->needs_refund,
            'created_at' => $p->created_at?->toIso8601String(),
            'refunds' => $refunds->where('payment_id', $p->id)->map(fn (Refund $r) => [
                'reference' => $r->reference,
                'amount_minor' => $r->amount_minor,
                'status' => $r->status->value,
                'kind' => $r->kind->value,
                'reason' => $r->reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
        ])->all();
    }
}
