<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Reporting\Contracts\StockAlertReader;
use Illuminate\Support\Facades\DB;

final class InventoryStockAlertReader implements StockAlertReader
{
    public function expiringLots(int $days): int
    {
        return StockLot::query()
            ->where('is_blocked', false)
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', now()->addDays($days)->toDateString())
            ->count();
    }

    public function outOfStockProducts(): int
    {
        $outOfStock = StockLot::query()
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('SUM(CASE WHEN is_blocked = 0 AND (expires_on IS NULL OR expires_on >= ?) THEN quantity_on_hand - quantity_reserved ELSE 0 END) <= 0', [now()->toDateString()]);

        return DB::query()->fromSub($outOfStock, 'out_of_stock')->count();
    }
}
