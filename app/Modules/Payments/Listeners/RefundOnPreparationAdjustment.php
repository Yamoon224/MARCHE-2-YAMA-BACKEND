<?php

declare(strict_types=1);

namespace App\Modules\Payments\Listeners;

use App\Modules\Orders\Events\OrderTotalAdjusted;
use App\Modules\Payments\Actions\RefundPaymentAction;
use App\Modules\Payments\Enums\RefundKind;

/** Pesée plus légère, rupture ou remplaçant moins cher : on rend la différence au client. */
final class RefundOnPreparationAdjustment
{
    public function __construct(private readonly RefundPaymentAction $refund) {}

    public function handle(OrderTotalAdjusted $event): void
    {
        // Écart positif (client à facturer en plus) : pas un remboursement, traité ailleurs.
        if ($event->deltaMinor >= 0) {
            return;
        }

        $this->refund->execute(
            $event->orderId, -$event->deltaMinor, RefundKind::Adjustment,
            'Écart de préparation (pesée, rupture ou remplacement)', "adjustment:order:{$event->orderId}", strict: false,
        );
    }
}
