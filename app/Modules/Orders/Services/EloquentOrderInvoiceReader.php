<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderInvoiceReader;
use App\Modules\Orders\DTO\InvoiceableLine;
use App\Modules\Orders\DTO\InvoiceableOrder;
use App\Modules\Orders\Models\Order;

final class EloquentOrderInvoiceReader implements OrderInvoiceReader
{
    public function find(int $orderId): ?InvoiceableOrder
    {
        $order = Order::with('lines')->find($orderId);

        if ($order === null) {
            return null;
        }

        return new InvoiceableOrder(
            orderId: $order->id,
            userId: $order->user_id,
            currency: $order->currency,
            deliveryFeeMinor: $order->delivery_fee_minor,
            discountMinor: $order->discount_minor,
            totalMinor: $order->total_minor,
            lines: $order->lines->map(fn ($l) => new InvoiceableLine(
                productId: $l->product_id, sku: $l->sku, name: $l->name, saleUnit: $l->sale_unit,
                quantity: $l->quantity, unitPriceMinor: $l->unit_price_minor,
                lineTotalMinor: $l->line_total_minor, vatRateBp: $l->vat_rate_bp,
            ))->all(),
        );
    }
}
