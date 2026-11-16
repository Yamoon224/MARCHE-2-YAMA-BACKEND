<?php

use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderTotalAdjusted;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->picker = staffUser(Role::Picker);
});

/** Démarre la préparation et retourne la liste. */
function started(array $ctx, ?User $picker = null)
{
    test()->actingAs($picker ?? test()->picker, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertOk();

    return $ctx['list']->refresh();
}

function pick(PickList $list, int $lineId, int $quantity, ?User $as = null)
{
    return test()->actingAs($as ?? test()->picker, 'sanctum')->postJson(lineUrl($list, $lineId, 'pick'), ['quantity' => $quantity]);
}

function complete(PickList $list, ?User $as = null)
{
    return test()->actingAs($as ?? test()->picker, 'sanctum')->postJson(pickUrl($list, '/complete'));
}

// ───────────────────────── prélèvement ─────────────────────────

it('counts pieces: exact is picked, fewer is short, more is refused', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 3]]);
    $list = started($ctx);
    $line = $list->lines->first();

    pick($list, $line->id, 3)->assertOk()->assertJsonPath('data.lines.0.status', 'picked');
    pick($list, $line->id, 2)->assertOk()->assertJsonPath('data.lines.0.status', 'short')->assertJsonPath('data.lines.0.picked_quantity', 2);
    pick($list, $line->id, 0)->assertOk()->assertJsonPath('data.lines.0.status', 'short');
    pick($list, $line->id, 4)->assertUnprocessable()->assertJsonValidationErrors('picking');
});

it('accepts a weight within the tolerance margin and refuses above it', function () {
    $ctx = orderInPreparation([['product' => weightProduct(900), 'quantity' => 1500]]); // 1,5 kg commandé
    $list = started($ctx);
    $line = $list->lines->first();

    expect($line->maxQuantity())->toBe(1650); // +10 %

    pick($list, $line->id, 1480)->assertOk()->assertJsonPath('data.lines.0.status', 'picked');
    pick($list, $line->id, 1650)->assertOk()->assertJsonPath('data.lines.0.picked_quantity', 1650);
    pick($list, $line->id, 1651)->assertUnprocessable();
    pick($list, $line->id, 0)->assertOk()->assertJsonPath('data.lines.0.status', 'short');
});

it('exposes the maximum servable quantity of each line', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 3], ['product' => weightProduct(), 'quantity' => 1000]]);

    $this->actingAs($this->picker, 'sanctum')->getJson(pickUrl($ctx['list']))
        ->assertOk()->assertJsonPath('data.lines.0.max_quantity', 3)->assertJsonPath('data.lines.1.max_quantity', 1100);
});

it('rejects invalid quantities', function (mixed $quantity) {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 3]]);
    $list = started($ctx);

    $this->actingAs($this->picker, 'sanctum')->postJson(lineUrl($list, $list->lines->first()->id, 'pick'), ['quantity' => $quantity])
        ->assertUnprocessable();
})->with(['negative' => [-1], 'fraction' => [1.5], 'text' => ['abc'], 'missing' => [null]]);

// ───────────────────────── remplacements ─────────────────────────

it('substitutes an article when the customer allowed it, capping the price at the ordered one', function () {
    $original = pieceProduct(1000, 10, 'Lait A');
    $pricier = pieceProduct(1400, 10, 'Lait B');
    $ctx = orderInPreparation([['product' => $original, 'quantity' => 2]]);
    $list = started($ctx);

    $this->actingAs($this->picker, 'sanctum')
        ->postJson(lineUrl($list, $list->lines->first()->id, 'substitute'), ['product_id' => $pricier->id, 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('data.lines.0.status', 'substituted')
        ->assertJsonPath('data.lines.0.final_name', 'Lait B')
        ->assertJsonPath('data.lines.0.final_unit_price_minor', 1000); // jamais plus cher que commandé
});

it('charges the cheaper price when the substitute costs less', function () {
    $original = pieceProduct(1000, 10, 'Lait A');
    $cheaper = pieceProduct(700, 10, 'Lait C');
    $ctx = orderInPreparation([['product' => $original, 'quantity' => 1]]);
    $list = started($ctx);

    $this->actingAs($this->picker, 'sanctum')
        ->postJson(lineUrl($list, $list->lines->first()->id, 'substitute'), ['product_id' => $cheaper->id, 'quantity' => 1])
        ->assertOk()->assertJsonPath('data.lines.0.final_unit_price_minor', 700);
});

it('refuses a substitution when the customer refused replacements', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1, 'substitution' => 'refuse']]);
    $list = started($ctx);

    $this->actingAs($this->picker, 'sanctum')
        ->postJson(lineUrl($list, $list->lines->first()->id, 'substitute'), ['product_id' => pieceProduct(100)->id, 'quantity' => 1])
        ->assertUnprocessable()->assertJsonPath('errors.picking.0', 'Le client a refusé tout remplacement pour cet article.');
});

it('refuses an unknown, inactive or differently-sold substitute', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $list = started($ctx);
    $url = lineUrl($list, $list->lines->first()->id, 'substitute');

    $inactive = pieceProduct();
    $inactive->update(['is_active' => false]);

    $this->actingAs($this->picker, 'sanctum')->postJson($url, ['product_id' => 999999, 'quantity' => 1])->assertUnprocessable();
    $this->actingAs($this->picker, 'sanctum')->postJson($url, ['product_id' => $inactive->id, 'quantity' => 1])->assertUnprocessable();
    $this->actingAs($this->picker, 'sanctum')->postJson($url, ['product_id' => weightProduct()->id, 'quantity' => 1])
        ->assertUnprocessable()->assertJsonPath('errors.picking.0', "Le remplaçant doit se vendre dans la même unité que l'article commandé.");
});

it('lets a new pick cancel an earlier substitution', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(1000, 10, 'Lait A'), 'quantity' => 1]]);
    $list = started($ctx);
    $line = $list->lines->first();

    $this->actingAs($this->picker, 'sanctum')->postJson(lineUrl($list, $line->id, 'substitute'), ['product_id' => pieceProduct(500)->id, 'quantity' => 1])->assertOk();
    pick($list, $line->id, 1)->assertOk()->assertJsonPath('data.lines.0.status', 'picked')->assertJsonPath('data.lines.0.final_name', null);
});

// ───────────────────────── clôture ─────────────────────────

it('cannot complete while articles remain to be handled', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1], ['product' => weightProduct(), 'quantity' => 500]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1)->assertOk();

    complete($list)->assertUnprocessable()->assertJsonPath('errors.picking.0', '1 article(s) restent à traiter avant de terminer la préparation.');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Preparing);
});

it('refunds the difference when the weighed articles are lighter than ordered', function () {
    Event::fake([OrderTotalAdjusted::class]);
    $tomatoes = weightProduct(900);
    $ctx = orderInPreparation([['product' => $tomatoes, 'quantity' => 1500]]); // 13,50 € payés
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1200); // 1,2 kg => 1 080

    $response = complete($list)->assertOk()->assertJsonPath('data.status', 'completed');

    expect($response->json('adjustment_minor'))->toBe(-270);
    $order = Order::find($ctx['order']->id);
    expect($order->status)->toBe(OrderStatus::Ready)
        ->and($order->total_minor)->toBe(1350)
        ->and($order->final_total_minor)->toBe(1080)
        ->and($order->adjustment_minor)->toBe(-270)
        ->and($order->lines->first()->final_quantity)->toBe(1200);

    Event::assertDispatched(OrderTotalAdjusted::class, fn ($e) => $e->orderId === $order->id
        && $e->chargedMinor === 1350 && $e->finalMinor === 1080 && $e->deltaMinor === -270);
});

it('asks for a top-up, within the margin, when the articles weigh more', function () {
    $ctx = orderInPreparation([['product' => weightProduct(900), 'quantity' => 1000]]); // 900 payés
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1100); // +10 % => 990

    expect(complete($list)->assertOk()->json('adjustment_minor'))->toBe(90);
    expect(Order::find($ctx['order']->id)->final_total_minor)->toBe(990);
});

it('does not adjust anything when the order is served exactly', function () {
    Event::fake([OrderTotalAdjusted::class]);
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 2]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 2);

    expect(complete($list)->assertOk()->json('adjustment_minor'))->toBe(0);
    Event::assertNotDispatched(OrderTotalAdjusted::class);
    expect(Order::find($ctx['order']->id)->final_total_minor)->toBe($ctx['total']);
});

it('reduces the total of an article that is short, delivery fee untouched', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(1000), 'quantity' => 3]]); // 3 000
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1);

    expect(complete($list)->assertOk()->json('adjustment_minor'))->toBe(-2000);
});

it('gives back the reserved stock of what could not be served', function () {
    $rice = pieceProduct(1000, 10);
    $lot = StockLot::where('product_id', $rice->id)->firstOrFail();
    $ctx = orderInPreparation([['product' => $rice, 'quantity' => 4]]);
    expect($lot->refresh()->quantity_reserved)->toBe(4);

    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1);
    complete($list)->assertOk();

    expect($lot->refresh()->quantity_reserved)->toBe(1)->and($lot->quantity_on_hand)->toBe(10);
});

it('releases everything for an article that is out of stock', function () {
    $rice = pieceProduct(1000, 10);
    $lot = StockLot::where('product_id', $rice->id)->firstOrFail();
    $ctx = orderInPreparation([['product' => $rice, 'quantity' => 4], ['product' => weightProduct(), 'quantity' => 500]]);

    $list = started($ctx);
    pick($list, $list->lines->first()->id, 0);
    pick($list, $list->lines->last()->id, 500);
    complete($list)->assertOk();

    expect($lot->refresh()->quantity_reserved)->toBe(0)
        ->and(StockReservation::where('stock_lot_id', $lot->id)->count())->toBe(0);
});

it('shrinks the reservation of the lots that expire last, keeping the earliest-expiring ones', function () {
    $rice = pieceProduct(1000, 0);
    $soon = stockUp($rice, 3, now()->addDays(2)->toDateString());
    $late = stockUp($rice, 10, now()->addDays(20)->toDateString());
    $ctx = orderInPreparation([['product' => $rice, 'quantity' => 6]]); // 3 (proche) + 3 (tardif)

    $list = started($ctx);
    pick($list, $list->lines->first()->id, 4);
    complete($list)->assertOk();

    expect($soon->refresh()->quantity_reserved)->toBe(3)->and($late->refresh()->quantity_reserved)->toBe(1);
});

it('reserves the substitute and releases the original on completion', function () {
    $original = pieceProduct(1000, 10, 'Lait A');
    $substitute = pieceProduct(900, 10, 'Lait B');
    $lotA = StockLot::where('product_id', $original->id)->firstOrFail();
    $lotB = StockLot::where('product_id', $substitute->id)->firstOrFail();
    $ctx = orderInPreparation([['product' => $original, 'quantity' => 2]]); // 2 000 payés

    $list = started($ctx);
    $this->actingAs($this->picker, 'sanctum')->postJson(lineUrl($list, $list->lines->first()->id, 'substitute'), ['product_id' => $substitute->id, 'quantity' => 2])->assertOk();

    expect(complete($list)->assertOk()->json('adjustment_minor'))->toBe(-200); // 2 × 900 = 1 800

    expect($lotA->refresh()->quantity_reserved)->toBe(0)->and($lotB->refresh()->quantity_reserved)->toBe(2);
    $line = Order::find($ctx['order']->id)->lines->first();
    expect($line->final_name)->toBe('Lait B')->and($line->final_product_id)->toBe($substitute->id)->and($line->product_id)->toBe($original->id);
});

it('changes nothing if the substitute is no longer available at completion', function () {
    $original = pieceProduct(1000, 10, 'Lait A');
    $substitute = pieceProduct(900, 1, 'Lait B');
    $lotA = StockLot::where('product_id', $original->id)->firstOrFail();
    $ctx = orderInPreparation([['product' => $original, 'quantity' => 2]]);
    $list = started($ctx);

    $this->actingAs($this->picker, 'sanctum')->postJson(lineUrl($list, $list->lines->first()->id, 'substitute'), ['product_id' => $substitute->id, 'quantity' => 2])->assertOk();

    complete($list)->assertUnprocessable()->assertJsonValidationErrors('picking');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Preparing)
        ->and(Order::find($ctx['order']->id)->final_total_minor)->toBeNull()
        ->and($lotA->refresh()->quantity_reserved)->toBe(2)
        ->and($list->refresh()->status->value)->toBe('in_progress');
});

it('cannot be completed twice', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1);

    complete($list)->assertOk();
    complete($list)->assertUnprocessable();
});

it('records who prepared the order in its history', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1);
    complete($list)->assertOk();

    $this->actingAs($this->picker, 'sanctum')->getJson("/api/v1/orders/{$ctx['order']->id}")
        ->assertForbidden(); // le préparateur n'a pas accès au détail des commandes

    $last = Order::find($ctx['order']->id)->history->last();
    expect($last->to_status)->toBe('ready')->and($last->user_id)->toBe($this->picker->id);
});

// ───────────────────────── conséquences en aval ─────────────────────────

it('leaves the issued invoice untouched: the adjustment is settled separately', function () {
    $ctx = orderInPreparation([['product' => weightProduct(900), 'quantity' => 1500]]);
    $invoice = Invoice::firstOrFail();
    $before = [$invoice->total_minor, $invoice->number];

    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1000);
    complete($list)->assertOk();

    $invoice->refresh();
    expect([$invoice->total_minor, $invoice->number])->toBe($before)
        ->and(Order::find($ctx['order']->id)->adjustment_minor)->toBeLessThan(0);
});

it('only takes out of stock what was actually served when the order is delivered', function () {
    $tomatoes = weightProduct(900, 10_000);
    $lot = StockLot::where('product_id', $tomatoes->id)->firstOrFail();
    $ctx = orderInPreparation([['product' => $tomatoes, 'quantity' => 2000]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 1700);
    complete($list)->assertOk();

    $transition = app(TransitionOrderAction::class);
    foreach ([OrderStatus::Assigned, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $status) {
        $transition->execute(Order::findOrFail($ctx['order']->id), $status, null);
    }

    $lot->refresh();
    expect($lot->quantity_on_hand)->toBe(10_000 - 1700)->and($lot->quantity_reserved)->toBe(0);
});

it('keeps the accounting ledger balanced after an adjustment', function () {
    $ctx = orderInPreparation([['product' => weightProduct(900), 'quantity' => 1500]]);
    $list = started($ctx);
    pick($list, $list->lines->first()->id, 900);
    complete($list)->assertOk();

    expect((int) DB::table('journal_lines')->sum('debit_minor'))->toBe((int) DB::table('journal_lines')->sum('credit_minor'));
});
