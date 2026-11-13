<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Actions\CommitReservationAction;
use App\Modules\Inventory\Actions\HoldReservationAction;
use App\Modules\Inventory\Actions\ReleaseReservationAction;
use App\Modules\Inventory\Actions\ReserveStockFefoAction;
use App\Modules\Inventory\Actions\ShrinkReservationAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Exceptions\InsufficientStock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class InventoryStockReservationPort implements StockReservationPort
{
    public function __construct(
        private readonly ReserveStockFefoAction $reserve,
        private readonly HoldReservationAction $hold,
        private readonly ReleaseReservationAction $release,
        private readonly ShrinkReservationAction $shrink,
        private readonly CommitReservationAction $commit,
    ) {}

    public function reserve(string $reference, array $quantities, ?CarbonInterface $expiresAt): void
    {
        $warehouse = Warehouse::where('is_active', true)->orderBy('id')->first();

        DB::transaction(function () use ($warehouse, $reference, $quantities, $expiresAt) {
            foreach ($quantities as $productId => $quantity) {
                if ($warehouse === null) {
                    throw InsufficientStock::forProduct($productId);
                }

                try {
                    $this->reserve->execute($warehouse->id, $productId, $quantity, $reference, $expiresAt);
                } catch (InsufficientStockException) {
                    throw InsufficientStock::forProduct($productId);
                }
            }
        });
    }

    public function hold(string $reference): void
    {
        $this->hold->execute($reference);
    }

    public function release(string $reference): void
    {
        $this->release->execute($reference);
    }

    public function shrink(string $reference, int $productId, int $newQuantity): void
    {
        $this->shrink->execute($reference, $productId, $newQuantity);
    }

    public function commit(string $reference): void
    {
        $this->commit->execute($reference);
    }
}
