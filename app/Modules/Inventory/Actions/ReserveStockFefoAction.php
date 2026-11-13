<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReserveStockFefoAction
{
    /**
     * Réserve la quantité demandée en puisant dans les lots selon FEFO (premier périmé, premier sorti).
     * Les lots périmés ou bloqués sont ignorés. Verrouillage pessimiste : pas de survente.
     *
     * @return Collection<int, StockReservation>
     */
    public function execute(int $warehouseId, int $productId, int $quantity, string $reference, ?CarbonInterface $expiresAt): Collection
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité à réserver doit être positive.');
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $reference, $expiresAt) {
            $lots = StockLot::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('is_blocked', false)
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString()))
                ->whereColumn('quantity_on_hand', '>', 'quantity_reserved')
                ->orderByRaw('expires_on IS NULL, expires_on ASC')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = $lots->sum(fn (StockLot $lot) => $lot->available());
            if ($available < $quantity) {
                throw InsufficientStockException::forProduct($productId, $quantity, (int) $available);
            }

            $remaining = $quantity;
            $reservations = new Collection;

            foreach ($lots as $lot) {
                if ($remaining === 0) {
                    break;
                }
                $take = min($remaining, $lot->available());
                $lot->increment('quantity_reserved', $take);
                $reservations->push(StockReservation::create([
                    'stock_lot_id' => $lot->id, 'reference' => $reference,
                    'quantity' => $take, 'expires_at' => $expiresAt,
                ]));
                $remaining -= $take;
            }

            return $reservations;
        });
    }
}
