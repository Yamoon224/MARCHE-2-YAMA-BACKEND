<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Events\LotRecalled;
use App\Modules\Inventory\Models\LotRecall;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Services\LotTraceability;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RecallLotAction
{
    public function __construct(private readonly LotTraceability $trace) {}

    /**
     * Rappelle un lot : il est bloqué immédiatement (plus aucune allocation FEFO) et la décision est tracée.
     * Idempotent : rappeler deux fois le même lot ne crée qu'un rappel (le premier motif est conservé) et
     * n'alerte les clients qu'une fois.
     */
    public function execute(StockLot $lot, string $reason, ?int $userId): LotRecall
    {
        $existing = LotRecall::where('stock_lot_id', $lot->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            $recall = DB::transaction(function () use ($lot, $reason, $userId) {
                StockLot::whereKey($lot->id)->lockForUpdate()->update(['is_blocked' => true]);

                return LotRecall::create(['stock_lot_id' => $lot->id, 'reason' => $reason, 'recalled_by' => $userId]);
            });
        } catch (UniqueConstraintViolationException) {
            return LotRecall::where('stock_lot_id', $lot->id)->firstOrFail();
        }

        $delivered = [];
        foreach ($this->trace->forLot($lot)['delivered'] as $row) {
            if ($row['customer_id'] !== null) {
                $delivered[] = ['customer_id' => $row['customer_id'], 'order_id' => $row['order_id']];
            }
        }

        LotRecalled::dispatch($lot->id, $lot->lot_code, $reason, $delivered);

        return $recall;
    }
}
