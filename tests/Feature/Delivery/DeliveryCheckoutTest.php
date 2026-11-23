<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Delivery\Models\SlotBooking;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->product = Product::factory()->create(['price_minor' => 3000]);
    stockUp($this->product, 100);
    $this->zone = DeliveryZone::factory()->create(['fee_minor' => 1500, 'free_above_minor' => 20000, 'min_order_minor' => 2000]);
    $this->slot = DeliverySlot::factory()->create(['delivery_zone_id' => $this->zone->id, 'capacity' => 2]);
});

function orderWithDelivery(int $quantity, ?int $slotId = null, ?int $zoneId = null, array $overrides = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => test()->product->id, 'quantity' => $quantity]],
        'fulfillment' => 'delivery',
        'delivery' => array_replace_recursive([
            'zone_id' => $zoneId ?? test()->zone->id,
            'slot_id' => $slotId ?? test()->slot->id,
            'phone' => '+2250700112233',
            'address' => ['neighborhood' => 'Cocody', 'landmark' => 'Derrière la pharmacie', 'latitude' => 5.35, 'longitude' => -4.0],
        ], $overrides),
    ]);
}

it('adds the zone fee to the total, computed by the server', function () {
    orderWithDelivery(2)->assertCreated()
        ->assertJsonPath('data.fulfillment', 'delivery')
        ->assertJsonPath('data.subtotal_minor', 6000)
        ->assertJsonPath('data.delivery_fee_minor', 1500)
        ->assertJsonPath('data.total_minor', 7500)
        ->assertJsonPath('data.delivery_slot_id', $this->slot->id);

    expect(Order::firstOrFail()->delivery_address['landmark'])->toBe('Derrière la pharmacie');
});

it('offers delivery above the free-delivery threshold', function () {
    orderWithDelivery(7)->assertCreated()->assertJsonPath('data.delivery_fee_minor', 0)->assertJsonPath('data.total_minor', 21000);
});

it('refuses an order below the zone minimum', function () {
    $this->zone->update(['min_order_minor' => 5000]);

    orderWithDelivery(1)->assertUnprocessable()->assertJsonValidationErrors('delivery'); // 3 000 < 5 000
    orderWithDelivery(2)->assertCreated(); // 6 000 >= 5 000

    expect(Order::count())->toBe(1)->and($this->slot->refresh()->booked)->toBe(1); // seule la commande valide existe
});

it('refuses an inactive or unknown zone', function () {
    $this->zone->update(['is_active' => false]);

    orderWithDelivery(2)->assertUnprocessable()->assertJsonValidationErrors('delivery');
    orderWithDelivery(2, null, 999999)->assertUnprocessable();
});

it('books a slot place and never exceeds its capacity', function () {
    orderWithDelivery(2)->assertCreated();
    orderWithDelivery(2, as: User::factory()->create())->assertCreated();
    orderWithDelivery(2, as: User::factory()->create())->assertUnprocessable()->assertJsonValidationErrors('delivery');

    expect($this->slot->refresh()->booked)->toBe(2)->and(SlotBooking::count())->toBe(2)->and(Order::count())->toBe(2);
});

it('rolls back the stock reservation when the slot is full', function () {
    $this->slot->update(['booked' => 2]);

    orderWithDelivery(2)->assertUnprocessable();

    expect(StockReservation::count())->toBe(0);
});

it('refuses a slot too close to now, in the past, or belonging to another zone', function () {
    $tooSoon = DeliverySlot::factory()->create(['delivery_zone_id' => $this->zone->id, 'starts_at' => now()->addMinutes(10), 'ends_at' => now()->addHours(2)]);
    $past = DeliverySlot::factory()->create(['delivery_zone_id' => $this->zone->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $otherZone = DeliverySlot::factory()->create();

    orderWithDelivery(2, $tooSoon->id)->assertUnprocessable();
    orderWithDelivery(2, $past->id)->assertUnprocessable();
    orderWithDelivery(2, $otherZone->id)->assertUnprocessable();

    expect(SlotBooking::count())->toBe(0);
});

it('frees the slot place when the order is cancelled', function () {
    $id = orderWithDelivery(2)->json('data.id');
    expect($this->slot->refresh()->booked)->toBe(1);

    app(TransitionOrderAction::class)->execute(Order::where('uuid', $id)->firstOrFail(), OrderStatus::Cancelled, null, 'Test');
    app(TransitionOrderAction::class); // pas de second effet à rejouer

    expect($this->slot->refresh()->booked)->toBe(0)->and(SlotBooking::count())->toBe(0);
});

it('frees the slot place when unpaid orders expire', function () {
    orderWithDelivery(2)->assertCreated();

    $this->travel(config('orders.payment_window_minutes') + 1)->minutes();
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($this->slot->refresh()->booked)->toBe(0);
});

it('requires the delivery details when delivery is chosen', function (array $delivery) {
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        'fulfillment' => 'delivery',
        'delivery' => $delivery,
    ])->assertUnprocessable();
})->with([
    'nothing' => [[]],
    'no phone' => [['zone_id' => 1, 'slot_id' => 1, 'address' => ['neighborhood' => 'Cocody']]],
    'bad phone' => [['zone_id' => 1, 'slot_id' => 1, 'phone' => 'abc', 'address' => ['neighborhood' => 'Cocody']]],
    'no neighbourhood' => [['zone_id' => 1, 'slot_id' => 1, 'phone' => '+2250700112233', 'address' => []]],
]);

it('keeps pickup free and slot-less', function () {
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertCreated()->assertJsonPath('data.fulfillment', 'pickup')->assertJsonPath('data.delivery_fee_minor', 0);

    expect(SlotBooking::count())->toBe(0);
});

it('invoices the delivery fee as its own line so that the detail equals the total', function () {
    $id = orderWithDelivery(2)->json('data.id');
    $payment = startPayment($this->customer, $id)->json('data.id');
    $ref = Payment::findOrFail($payment)->provider_reference;
    sendWebhook(webhookFor($ref, 'succeeded', 7500, 'evt-delivery'))->assertOk();

    $invoice = Invoice::with('lines')->firstOrFail();
    expect($invoice->total_minor)->toBe(7500)
        ->and($invoice->lines->sum('line_total_minor'))->toBe(7500)
        ->and($invoice->lines->pluck('name')->contains('Frais de livraison'))->toBeTrue();
});

it('lists only bookable slots of a zone, publicly', function () {
    DeliverySlot::factory()->create(['delivery_zone_id' => $this->zone->id, 'capacity' => 1, 'booked' => 1]);
    DeliverySlot::factory()->create(['delivery_zone_id' => $this->zone->id, 'starts_at' => now()->addMinutes(5), 'ends_at' => now()->addHours(2)]);

    $this->getJson("/api/v1/delivery/zones/{$this->zone->id}/slots")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->slot->id)->assertJsonPath('data.0.remaining', 2);
});

it('lists active zones publicly', function () {
    DeliveryZone::factory()->create(['is_active' => false]);

    $this->getJson('/api/v1/delivery/zones')->assertOk()->assertJsonCount(1, 'data');
});

it('lets only authorised staff manage zones and slots', function () {
    $zonePayload = ['name' => 'Marcory', 'fee_minor' => 1000, 'free_above_minor' => 15000];
    $slotPayload = fn () => [
        'delivery_zone_id' => $this->zone->id, 'starts_at' => now()->addDays(2)->toDateTimeString(),
        'ends_at' => now()->addDays(2)->addHours(2)->toDateTimeString(), 'capacity' => 8,
    ];

    asGuest();
    $this->postJson('/api/v1/delivery/zones', $zonePayload)->assertUnauthorized();
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/delivery/zones', $zonePayload)->assertForbidden();

    $operator = User::factory()->role(Role::OrderOperator)->create();
    $this->actingAs($operator, 'sanctum')->postJson('/api/v1/delivery/zones', $zonePayload)->assertCreated();
    $this->actingAs($operator, 'sanctum')->postJson('/api/v1/delivery/slots', $slotPayload())->assertCreated()->assertJsonPath('data.remaining', 8);
    $this->actingAs($operator, 'sanctum')->postJson('/api/v1/delivery/slots', [...$slotPayload(), 'capacity' => 0])->assertUnprocessable();
    $this->actingAs($operator, 'sanctum')->postJson('/api/v1/delivery/slots', [...$slotPayload(), 'starts_at' => now()->subDay()->toDateTimeString()])->assertUnprocessable();
});
