<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Orders\Contracts\OrderCustomerDirectory;
use Illuminate\Support\Facades\DB;

/** Traçabilité d'un lot : qui l'a reçu (à prévenir) et quelles commandes en attente le réservent (à réallouer). */
final class LotTraceability
{
    public function __construct(private readonly OrderCustomerDirectory $orders) {}

    /**
     * @return array{delivered: list<array{order_id: int, customer_id: int|null, quantity: int}>, reserved: list<array{order_id: int, customer_id: int|null, quantity: int}>}
     */
    public function forLot(StockLot $lot): array
    {
        // Sorties physiques (livrées) : le mouvement « vente » porte la référence de la commande.
        $delivered = DB::table('stock_movements')->where('stock_lot_id', $lot->id)->where('type', 'sale')
            ->where('reason', 'like', 'order:%')->selectRaw('reason, SUM(-quantity) as qty')->groupBy('reason')->get()
            ->mapWithKeys(fn ($r) => [(int) substr((string) $r->reason, 6) => (int) $r->qty]);

        // Réservations en cours : commandes pas encore livrées qui comptent sur ce lot.
        $reserved = DB::table('stock_reservations')->where('stock_lot_id', $lot->id)->where('reference', 'like', 'order:%')
            ->selectRaw('reference, SUM(quantity) as qty')->groupBy('reference')->get()
            ->mapWithKeys(fn ($r) => [(int) substr((string) $r->reference, 6) => (int) $r->qty]);

        $customers = $this->orders->customersOf($delivered->keys()->merge($reserved->keys())->unique()->values()->all());

        $shape = fn ($map) => $map->map(fn (int $qty, int $orderId) => [
            'order_id' => $orderId, 'customer_id' => $customers[$orderId] ?? null, 'quantity' => $qty,
        ])->sortBy('order_id')->values()->all();

        return ['delivered' => $shape($delivered), 'reserved' => $shape($reserved)];
    }
}
