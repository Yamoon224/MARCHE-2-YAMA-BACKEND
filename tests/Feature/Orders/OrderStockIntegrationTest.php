<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Orders\Actions\ExpireUnpaidOrdersAction;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Contracts\StockReservationPort;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->product = Product::factory()->create(['price_minor' => 1000]);
    $this->lot = stockUp($this->product, 10);
});

function placeOrder(int $quantity, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => test()->product->id, 'quantity' => $quantity]],
    ]);
}

function move(int|string $orderId, string $to, ?string $reason = null): void
{
    $orderId = is_string($orderId) ? internalOrderId($orderId) : $orderId;
    app(TransitionOrderAction::class)->execute(Order::findOrFail($orderId), OrderStatus::from($to), null, $reason);
}

it('reserves stock when an order is placed', function () {
    placeOrder(4)->assertCreated();

    expect($this->lot->refresh()->quantity_reserved)->toBe(4)
        ->and($this->lot->quantity_on_hand)->toBe(10)
        ->and($this->lot->available())->toBe(6);
});

it('refuses an order that exceeds the available stock and leaves nothing behind', function () {
    placeOrder(11)->assertUnprocessable()->assertJsonValidationErrors('items');

    expect(Order::count())->toBe(0)
        ->and(StockReservation::count())->toBe(0)
        ->and($this->lot->refresh()->quantity_reserved)->toBe(0);
});

it('cannot oversell the last items across successive orders', function () {
    placeOrder(7)->assertCreated();
    placeOrder(4)->assertUnprocessable();
    placeOrder(3)->assertCreated();

    expect($this->lot->refresh()->available())->toBe(0);
});

it('rolls back every line when one of them lacks stock', function () {
    $other = Product::factory()->create();
    stockUp($other, 1);

    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', ['items' => [
        ['product_id' => $this->product->id, 'quantity' => 5],
        ['product_id' => $other->id, 'quantity' => 2],
    ]])->assertUnprocessable();

    expect($this->lot->refresh()->quantity_reserved)->toBe(0)->and(Order::count())->toBe(0);
});

it('releases the stock when the order is cancelled', function () {
    $id = internalOrderId(placeOrder(4)->json('data.id'));

    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/orders/'.orderUuid($id).'/transitions', ['to' => 'cancelled', 'reason' => 'Test'])->assertOk();

    expect($this->lot->refresh()->quantity_reserved)->toBe(0)->and(StockReservation::count())->toBe(0);
});

it('keeps the reservation without expiry once the order is paid', function () {
    $id = internalOrderId(placeOrder(4)->json('data.id'));
    move($id, 'paid');

    expect(StockReservation::whereNotNull('expires_at')->count())->toBe(0)
        ->and($this->lot->refresh()->quantity_reserved)->toBe(4);
});

it('removes the stock physically on delivery and records a sale movement', function () {
    $id = internalOrderId(placeOrder(4)->json('data.id'));
    foreach (['paid', 'confirmed', 'preparing', 'ready', 'assigned', 'out_for_delivery', 'delivered'] as $status) {
        move($id, $status);
    }

    $this->lot->refresh();
    expect($this->lot->quantity_on_hand)->toBe(6)
        ->and($this->lot->quantity_reserved)->toBe(0)
        ->and((int) StockMovement::where('stock_lot_id', $this->lot->id)->where('type', 'sale')->sum('quantity'))->toBe(-4);
});

it('puts the stock back when the delivery ends in a return to the store', function () {
    $id = internalOrderId(placeOrder(4)->json('data.id'));
    foreach (['paid', 'confirmed', 'preparing', 'ready', 'assigned', 'out_for_delivery'] as $status) {
        move($id, $status);
    }
    move($id, 'delivery_failed', 'Client absent');
    move($id, 'returned', 'Retour magasin');

    expect($this->lot->refresh()->quantity_reserved)->toBe(0)->and($this->lot->quantity_on_hand)->toBe(10);
});

it('cancels unpaid orders after the payment window and frees their stock', function () {
    $unpaid = internalOrderId(placeOrder(3)->json('data.id'));
    $paid = internalOrderId(placeOrder(2)->json('data.id'));
    move($paid, 'paid');

    $this->travel(config('orders.payment_window_minutes') + 1)->minutes();
    $count = app(ExpireUnpaidOrdersAction::class)->execute();

    expect($count)->toBe(1)
        ->and(Order::find($unpaid)->status)->toBe(OrderStatus::Cancelled)
        ->and(Order::find($paid)->status)->toBe(OrderStatus::Paid)
        ->and($this->lot->refresh()->quantity_reserved)->toBe(2);
});

it('does not expire orders still inside the payment window', function () {
    placeOrder(3);

    expect(app(ExpireUnpaidOrdersAction::class)->execute())->toBe(0);
});

it('is idempotent: replaying a cancellation does not release twice', function () {
    $id = internalOrderId(placeOrder(4)->json('data.id'));
    placeOrder(2);
    move($id, 'cancelled', 'Test');

    app(StockReservationPort::class)->release("order:{$id}");

    expect($this->lot->refresh()->quantity_reserved)->toBe(2);
});
