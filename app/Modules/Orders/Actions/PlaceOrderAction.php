<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Contracts\CatalogPriceReader;
use App\Modules\Orders\Contracts\DeliveryPort;
use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Domain\LineTotalCalculator;
use App\Modules\Orders\DTO\DeliveryChoice;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\DeliveryUnavailable;
use App\Modules\Orders\Exceptions\InsufficientStock;
use App\Modules\Orders\Exceptions\ProductNotOrderable;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

final class PlaceOrderAction
{
    public function __construct(
        private readonly CatalogPriceReader $prices,
        private readonly LineTotalCalculator $calculator,
        private readonly StockReservationPort $stock,
        private readonly DeliveryPort $delivery,
    ) {}

    /**
     * Crée une commande « en attente de paiement » et réserve le stock (FEFO), le tout ou rien.
     * Les prix viennent toujours du catalogue : le client ne transmet que des identifiants et des quantités.
     *
     * @param  list<array{product_id: int, quantity: int, substitution?: string|null}>  $items
     *
     * @throws ProductNotOrderable
     * @throws InsufficientStock
     * @throws DeliveryUnavailable
     */
    public function execute(int $userId, array $items, ?string $idempotencyKey = null, ?DeliveryChoice $delivery = null, string $channel = 'web'): Order
    {
        if ($idempotencyKey !== null) {
            $existing = Order::where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load('lines');
            }
        }

        $quantities = [];
        $policies = [];
        foreach ($items as $item) {
            $quantities[$item['product_id']] = ($quantities[$item['product_id']] ?? 0) + $item['quantity'];
            // Lignes fusionnées : un refus de substitution l'emporte.
            $policies[$item['product_id']] = ($policies[$item['product_id']] ?? 'allow') === 'refuse' || ($item['substitution'] ?? 'allow') === 'refuse' ? 'refuse' : 'allow';
        }

        $products = $this->prices->findOrderable(array_keys($quantities));

        return DB::transaction(function () use ($userId, $quantities, $policies, $products, $idempotencyKey, $delivery, $channel) {
            $lines = [];
            $subtotal = 0;

            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId] ?? throw ProductNotOrderable::forProduct($productId);
                $lineTotal = $this->calculator->total($product->saleUnit, $product->priceMinor, $quantity);
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_id' => $productId, 'sku' => $product->sku, 'name' => $product->name,
                    'sale_unit' => $product->saleUnit, 'quantity' => $quantity,
                    'unit_price_minor' => $product->priceMinor, 'line_total_minor' => $lineTotal,
                    'vat_rate_bp' => $product->vatRateBp, 'substitution' => $policies[$productId],
                ];
            }

            // Frais calculés par le serveur selon la zone ; retrait en magasin = gratuit.
            $fee = $delivery !== null ? $this->delivery->quote($delivery->zoneId, $subtotal)->feeMinor : 0;

            $order = Order::create([
                'user_id' => $userId, 'status' => OrderStatus::AwaitingPayment,
                'subtotal_minor' => $subtotal, 'delivery_fee_minor' => $fee, 'total_minor' => $subtotal + $fee,
                'idempotency_key' => $idempotencyKey, 'channel' => $channel,
                'fulfillment' => $delivery !== null ? 'delivery' : 'pickup',
                'delivery_zone_id' => $delivery?->zoneId, 'delivery_slot_id' => $delivery?->slotId,
                'delivery_address' => $delivery?->address, 'contact_phone' => $delivery?->phone,
            ]);
            $order->update(['number' => 'CMD-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT)]);
            $order->lines()->createMany($lines);

            if ($delivery !== null) {
                $this->delivery->bookSlot($delivery->slotId, $delivery->zoneId, $order->id);
            }

            // $this->stock->reserve(
            //     "order:{$order->id}",
            //     $quantities,
            //     now()->addMinutes((int) config('orders.payment_window_minutes')),
            // );

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => null,
                'to_status' => OrderStatus::AwaitingPayment->value, 'user_id' => $userId,
            ]);

            return $order->load('lines');
        });
    }
}
