<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Support\Facades\DB;

final class ShrinkReservationAction
{
    /**
     * Réduit la quantité réservée d'un produit pour une référence (rupture, pesée plus légère).
     * On garde les lots qui périment en premier et on libère les derniers (FEFO). Sans effet si déjà inférieure.
     */
    public function execute(string $reference, int $productId, int $newQuantity): void
    {
        DB::transaction(function () use ($reference, $productId, $newQuantity) {
            $reservations = StockReservation::query()
                ->where('reference', $reference)
                ->join('stock_lots', 'stock_lots.id', '=', 'stock_reservations.stock_lot_id')
                ->where('stock_lots.product_id', $productId)
                ->orderByRaw('stock_lots.expires_on IS NULL DESC, stock_lots.expires_on DESC')
                ->select('stock_reservations.*')
                ->lockForUpdate()
                ->get();

            $excess = (int) $reservations->sum('quantity') - $newQuantity;

            foreach ($reservations as $reservation) {
                if ($excess <= 0) {
                    break;
                }

                $release = min($excess, $reservation->quantity);
                StockLot::whereKey($reservation->stock_lot_id)->decrement('quantity_reserved', $release);

                if ($release === $reservation->quantity) {
                    $reservation->delete();
                } else {
                    $reservation->update(['quantity' => $reservation->quantity - $release]);
                }

                $excess -= $release;
            }
        });
    }
}
