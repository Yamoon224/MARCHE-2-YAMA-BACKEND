<?php

use App\Modules\Inventory\Actions\ReceiveStockAction;
use App\Modules\Inventory\Actions\ReleaseExpiredReservationsAction;
use App\Modules\Inventory\Actions\ReserveStockFefoAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
});

function lot(int $warehouseId, string $code, string $expires, int $qty, array $extra = []): StockLot
{
    return StockLot::factory()->create([
        'warehouse_id' => $warehouseId, 'product_id' => 1, 'lot_code' => $code,
        'expires_on' => $expires, 'quantity_on_hand' => $qty, ...$extra,
    ]);
}

it('receives stock and records a movement', function () {
    $lot = app(ReceiveStockAction::class)->execute($this->warehouse->id, 1, 'L1', now()->addDays(5)->toDateString(), 50);

    expect($lot->quantity_on_hand)->toBe(50)
        ->and((int) StockMovement::where('stock_lot_id', $lot->id)->sum('quantity'))->toBe(50);

    app(ReceiveStockAction::class)->execute($this->warehouse->id, 1, 'L1', null, 25);
    expect($lot->refresh()->quantity_on_hand)->toBe(75);
});

it('allocates the earliest-expiring lot first (FEFO)', function () {
    $late = lot($this->warehouse->id, 'LATE', now()->addDays(20)->toDateString(), 10);
    $soon = lot($this->warehouse->id, 'SOON', now()->addDays(2)->toDateString(), 10);

    app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 15, 'CMD-1', now()->addMinutes(15));

    expect($soon->refresh()->quantity_reserved)->toBe(10)
        ->and($late->refresh()->quantity_reserved)->toBe(5);
});

it('never allocates expired or blocked lots', function () {
    lot($this->warehouse->id, 'OLD', now()->subDay()->toDateString(), 100);
    lot($this->warehouse->id, 'BLK', now()->addDays(3)->toDateString(), 100, ['is_blocked' => true]);
    $ok = lot($this->warehouse->id, 'OK', now()->addDays(4)->toDateString(), 5);

    expect(fn () => app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 6, 'CMD-2', now()->addMinutes(15)))
        ->toThrow(InsufficientStockException::class);

    app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 5, 'CMD-2', now()->addMinutes(15));
    expect($ok->refresh()->available())->toBe(0);
});

it('does not oversell and leaves reservations untouched on failure', function () {
    $lot = lot($this->warehouse->id, 'A', now()->addDays(3)->toDateString(), 3);

    app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 2, 'CMD-3', now()->addMinutes(15));

    expect(fn () => app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 2, 'CMD-4', now()->addMinutes(15)))
        ->toThrow(InsufficientStockException::class);

    expect($lot->refresh()->quantity_reserved)->toBe(2)->and($lot->available())->toBeGreaterThanOrEqual(0);
});

it('releases expired reservations', function () {
    $lot = lot($this->warehouse->id, 'A', now()->addDays(3)->toDateString(), 10);
    app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 4, 'CMD-5', now()->subMinute());
    app(ReserveStockFefoAction::class)->execute($this->warehouse->id, 1, 2, 'CMD-6', now()->addHour());

    expect(app(ReleaseExpiredReservationsAction::class)->execute())->toBe(1)
        ->and($lot->refresh()->quantity_reserved)->toBe(2);
});
