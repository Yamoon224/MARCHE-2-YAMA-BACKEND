<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;

final class ExpireUnpaidOrdersAction
{
    public function __construct(private readonly TransitionOrderAction $transition) {}

    /** Annule les commandes non payées dans le délai ; la libération du stock suit par événement. */
    public function execute(): int
    {
        $cutoff = now()->subMinutes((int) config('orders.payment_window_minutes'));
        $expired = 0;

        $orders = Order::query()
            ->whereIn('status', [OrderStatus::AwaitingPayment, OrderStatus::PaymentFailed])
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($orders as $order) {
            try {
                $this->transition->execute($order, OrderStatus::Cancelled, null, 'Paiement expiré');
                $expired++;
            } catch (InvalidOrderTransition) {
                // Statut modifié entre-temps (paiement reçu) : on ne touche à rien.
            }
        }

        return $expired;
    }
}
