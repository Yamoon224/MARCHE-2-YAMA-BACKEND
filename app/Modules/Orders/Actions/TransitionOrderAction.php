<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Domain\OrderStateMachine;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

final class TransitionOrderAction
{
    public function __construct(private readonly OrderStateMachine $machine) {}

    /**
     * Seul point d'entrée pour changer un statut : verrouille la ligne, valide la transition,
     * historise (qui, quand, motif) puis publie l'événement de domaine.
     */
    public function execute(Order $order, OrderStatus $to, ?int $userId, ?string $reason = null): Order
    {
        $from = DB::transaction(function () use ($order, $to, $userId, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            $this->machine->assertCanTransition($from, $to, $reason);

            $locked->update(['status' => $to]);
            OrderStatusHistory::create([
                'order_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value,
                'reason' => $reason, 'user_id' => $userId,
            ]);

            return $from;
        });

        $order->refresh();
        OrderStatusChanged::dispatch($order->id, $from, $to);

        return $order;
    }
}
