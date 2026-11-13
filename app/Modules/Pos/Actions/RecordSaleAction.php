<?php

declare(strict_types=1);

namespace App\Modules\Pos\Actions;

use App\Modules\Orders\Contracts\PosOrderPort;
use App\Modules\Orders\Exceptions\InsufficientStock;
use App\Modules\Orders\Exceptions\ProductNotOrderable;
use App\Modules\Payments\Contracts\PosPaymentRecorder;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Pos\Exceptions\PosNotAllowed;
use App\Modules\Pos\Models\PosSale;
use App\Modules\Pos\Models\PosSession;
use Illuminate\Support\Facades\DB;

final class RecordSaleAction
{
    public function __construct(
        private readonly PosOrderPort $orders,
        private readonly PosPaymentRecorder $payments,
    ) {}

    /**
     * Vente au comptoir : commande, encaissement et remise au client en une seule opération, tout ou rien. Les prix
     * viennent du catalogue ; le caissier ne transmet que des articles, des quantités et le moyen de paiement.
     * Une même clé d'idempotence ne crée jamais deux ventes (double appui, réseau coupé).
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @param  'cash'|'mobile_money'|'card'  $tender
     *
     * @throws PosNotAllowed
     * @throws ProductNotOrderable
     * @throws InsufficientStock
     * @throws PaymentNotAllowed
     */
    public function execute(int $cashierId, array $items, string $tender, ?int $tenderedMinor, ?string $idempotencyKey): PosSale
    {
        $open = PosSession::where('cashier_id', $cashierId)->whereNull('closed_at')->first();
        if ($open === null) {
            throw PosNotAllowed::noOpenSession();
        }

        if ($idempotencyKey !== null) {
            $existing = PosSale::where('pos_session_id', $open->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($open, $cashierId, $items, $tender, $tenderedMinor, $idempotencyKey) {
            // Verrou : la caisse ne peut pas être fermée pendant qu'une vente s'enregistre.
            $session = PosSession::whereKey($open->id)->lockForUpdate()->firstOrFail();
            if (! $session->isOpen()) {
                throw PosNotAllowed::noOpenSession();
            }

            $order = $this->orders->place($cashierId, $items);

            $tendered = $tender === 'cash' ? ($tenderedMinor ?? $order->totalMinor) : $order->totalMinor;
            if ($tendered < $order->totalMinor) {
                throw PosNotAllowed::insufficientTender($order->totalMinor);
            }

            $this->payments->record($order->orderId, $tender, $order->totalMinor);
            $this->orders->completeInStore($order->orderId, $cashierId);

            return PosSale::create([
                'pos_session_id' => $session->id, 'order_id' => $order->orderId, 'order_number' => $order->number,
                'tender' => $tender, 'total_minor' => $order->totalMinor, 'tendered_minor' => $tendered,
                'change_minor' => $tendered - $order->totalMinor, 'lines' => $order->lines, 'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
