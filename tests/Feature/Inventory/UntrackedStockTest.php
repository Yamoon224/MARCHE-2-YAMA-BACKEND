<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\InventoryStockReservationPort;
use App\Modules\Orders\Exceptions\InsufficientStock;

it('lets a product without stock tracking be ordered with no lot', function () {
    $free = Product::factory()->create(['track_stock' => false]);

    app(InventoryStockReservationPort::class)->reserve('ORD-FREE', [$free->id => 3], null);

    expect(true)->toBeTrue();
});

it('still refuses a tracked product without stock', function () {
    $tracked = Product::factory()->create(['track_stock' => true]);

    expect(fn () => app(InventoryStockReservationPort::class)->reserve('ORD-TRACKED', [$tracked->id => 1], null))
        ->toThrow(InsufficientStock::class);
});
