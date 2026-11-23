<?php

use App\Modules\Fulfillment\Actions\CreatePickListAction;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

it('creates the pick list when the order goes into preparation, and not before', function () {
    $rice = pieceProduct();
    $customer = User::factory()->create();
    $orderId = internalOrderId(test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $rice->id, 'quantity' => 2]],
    ])->json('data.id'));

    expect(PickList::count())->toBe(0);

    $ctx = orderInPreparation([['product' => $rice, 'quantity' => 3]]);

    expect($ctx['list']->order_id)->toBe($ctx['order']->id)
        ->and($ctx['list']->lines)->toHaveCount(1)
        ->and($ctx['list']->lines->first()->ordered_quantity)->toBe(3)
        ->and($ctx['list']->status->value)->toBe('open')
        ->and(PickList::where('order_id', $orderId)->exists())->toBeFalse();
});

it('creates it only once, even if the event is replayed', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);

    app(CreatePickListAction::class)->execute($ctx['order']->id);
    app(CreatePickListAction::class)->execute($ctx['order']->id);

    expect(PickList::count())->toBe(1);
});

it('snapshots the substitution instruction of each line', function () {
    $ctx = orderInPreparation([
        ['product' => pieceProduct(), 'quantity' => 1, 'substitution' => 'refuse'],
        ['product' => weightProduct(), 'quantity' => 500],
    ]);

    expect($ctx['list']->lines->pluck('substitution')->all())->toBe(['refuse', 'allow']);
});

it('is reserved to staff allowed to prepare orders', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);

    asGuest();
    $this->getJson('/api/v1/fulfillment/pick-lists')->assertUnauthorized();
    $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/fulfillment/pick-lists')->assertForbidden();
    $this->actingAs(staffUser(Role::Courier), 'sanctum')->getJson(pickUrl($ctx['list']))->assertForbidden();
    $this->actingAs(staffUser(Role::Accountant), 'sanctum')->getJson('/api/v1/fulfillment/pick-lists')->assertForbidden();

    foreach ([Role::Picker, Role::OrderOperator, Role::Admin] as $role) {
        $this->actingAs(staffUser($role), 'sanctum')->getJson('/api/v1/fulfillment/pick-lists')->assertOk()->assertJsonCount(1, 'data');
    }
});

it('lists open and in-progress preparations only by default', function () {
    $open = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $done = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $done['list']->update(['status' => 'completed']);

    $picker = staffUser(Role::Picker);

    $this->actingAs($picker, 'sanctum')->getJson('/api/v1/fulfillment/pick-lists')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $open['list']->id);
    $this->actingAs($picker, 'sanctum')->getJson('/api/v1/fulfillment/pick-lists?status=completed')
        ->assertOk()->assertJsonPath('data.0.id', $done['list']->id);
});

it('lets a picker take a preparation and blocks the others', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $anna = staffUser(Role::Picker);
    $ben = staffUser(Role::Picker);

    $this->actingAs($anna, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))
        ->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.picker_id', $anna->id);

    // reprendre sa propre préparation est sans effet
    $this->actingAs($anna, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertOk();

    $this->actingAs($ben, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertUnprocessable();
    expect($ctx['list']->refresh()->picker_id)->toBe($anna->id);
});

it('refuses to prepare before starting', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $lineId = $ctx['list']->lines->first()->id;

    $this->actingAs(staffUser(Role::Picker), 'sanctum')
        ->postJson(lineUrl($ctx['list'], $lineId, 'pick'), ['quantity' => 1])->assertUnprocessable();
});

it('keeps another picker\'s preparation off limits, except for managers', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 2]]);
    $lineId = $ctx['list']->lines->first()->id;
    $owner = staffUser(Role::Picker);

    $this->actingAs($owner, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertOk();

    $this->actingAs(staffUser(Role::Picker), 'sanctum')
        ->postJson(lineUrl($ctx['list'], $lineId, 'pick'), ['quantity' => 1])->assertForbidden();
    $this->actingAs(staffUser(Role::Picker), 'sanctum')->postJson(pickUrl($ctx['list'], '/complete'))->assertForbidden();

    $this->actingAs(staffUser(Role::OrderOperator), 'sanctum')
        ->postJson(lineUrl($ctx['list'], $lineId, 'pick'), ['quantity' => 2])->assertOk();
});

it('does not let a line of another preparation be addressed through this one', function () {
    $a = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $b = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $picker = staffUser(Role::Picker);

    $this->actingAs($picker, 'sanctum')->postJson(pickUrl($a['list'], '/start'))->assertOk();

    $this->actingAs($picker, 'sanctum')
        ->postJson(lineUrl($a['list'], $b['list']->lines->first()->id, 'pick'), ['quantity' => 1])->assertNotFound();
});

it('cannot start a completed preparation', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);
    $ctx['list']->update(['status' => 'completed']);

    $this->actingAs(staffUser(Role::Picker), 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertUnprocessable();
});

it('cannot mark a preparing order ready through the orders API, whatever the role', function () {
    $ctx = orderInPreparation([['product' => pieceProduct(), 'quantity' => 1]]);

    foreach ([Role::Picker, Role::OrderOperator, Role::Admin] as $role) {
        $this->actingAs(staffUser($role), 'sanctum')
            ->postJson('/api/v1/orders/'.orderUuid($ctx['order']->id).'/transitions', ['to' => 'ready'])
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Preparing);
});
