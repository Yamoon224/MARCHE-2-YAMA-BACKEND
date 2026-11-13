<?php

use App\Modules\Delivery\Events\DeliveryCompleted;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryLocation;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Invoicing\Actions\GenerateCreditNoteAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Event;

function confirm(Delivery $delivery, User $courier, string $code, int $cash = 0)
{
    return courierCall($courier, $delivery, 'confirm', ['code' => $code, 'cash_collected_minor' => $cash]);
}

// ───────────────────────── remise avec code ─────────────────────────

it('hands over a prepaid order with the right code', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.3, 'longitude' => -4.0])->assertNoContent();

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk()->assertJsonPath('data.status', 'delivered');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Delivered)
        ->and($ctx['delivery']->refresh()->delivered_at)->not->toBeNull()
        ->and(DeliveryLocation::count())->toBe(0); // la position ne se conserve pas après la remise
});

it('takes the stock out when the order is handed over', function () {
    $ctx = readyDeliveryOrder('mobile_money', 5000, 3);
    $lot = StockLot::where('quantity_reserved', 3)->firstOrFail();
    $courier = outForDelivery($ctx['delivery']);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();

    expect($lot->refresh()->quantity_on_hand)->toBe(47)->and($lot->quantity_reserved)->toBe(0);
});

it('refuses a wrong code, counting the attempts', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    $wrong = $ctx['delivery']->confirmation_code === '000000' ? '111111' : '000000';

    confirm($ctx['delivery'], $courier, $wrong)->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Code de confirmation incorrect (4 essai(s) restant(s)).');
    confirm($ctx['delivery'], $courier, $wrong)->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Code de confirmation incorrect (3 essai(s) restant(s)).');

    expect($ctx['delivery']->refresh()->code_attempts)->toBe(2)->and(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::OutForDelivery);

    // le bon code passe encore tant que le verrou n'est pas atteint
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();
});

it('locks the code after 5 wrong attempts, even for the right code, until a dispatcher unlocks', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    $wrong = $ctx['delivery']->confirmation_code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        confirm($ctx['delivery'], $courier, $wrong)->assertUnprocessable();
    }

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)
        ->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Trop de codes erronés : contactez la régulation pour débloquer la livraison.');
    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::OutForDelivery);

    $this->actingAs($courier, 'sanctum')->postJson("/api/v1/delivery/deliveries/{$ctx['delivery']->id}/unlock")->assertForbidden();
    $this->actingAs(dispatcher(), 'sanctum')->postJson("/api/v1/delivery/deliveries/{$ctx['delivery']->id}/unlock")->assertOk()->assertJsonPath('data.code_locked', false);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();
});

it('validates the format of the code and of the cash amount', function (array $body, string $field) {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);

    courierCall($courier, $ctx['delivery'], 'confirm', $body)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($ctx['delivery']->refresh()->code_attempts)->toBe(0); // un format invalide ne consomme pas d'essai
})->with([
    'short code' => [['code' => '123', 'cash_collected_minor' => 0], 'code'],
    'letters' => [['code' => 'abcdef', 'cash_collected_minor' => 0], 'code'],
    'no cash field' => [['code' => '123456'], 'cash_collected_minor'],
    'negative cash' => [['code' => '123456', 'cash_collected_minor' => -1], 'cash_collected_minor'],
]);

it('cannot be confirmed twice', function () {
    Event::fake([DeliveryCompleted::class]);
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertUnprocessable();

    Event::assertDispatchedTimes(DeliveryCompleted::class, 1);
});

it('hides the code once the delivery is over', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();

    $this->actingAs($ctx['customer'], 'sanctum')->getJson("/api/v1/orders/{$ctx['order']->id}/tracking")
        ->assertOk()->assertJsonPath('data.status', 'delivered')->assertJsonPath('data.confirmation_code', null)->assertJsonPath('data.location', null);
});

// ───────────────────────── encaissement en espèces ─────────────────────────

it('expects no cash for an order already paid online', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 500)
        ->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Montant encaissé incorrect : 0 F CFA attendus.');
    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::OutForDelivery);
});

it('collects exactly the cash due for a cash on delivery order and books it in the till', function () {
    $ctx = readyDeliveryOrder('cash_on_delivery', 5000, 2); // 10 000 + 1 500 de livraison
    expect($ctx['total'])->toBe(11500);
    $courier = outForDelivery($ctx['delivery']);

    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-tour')->assertJsonPath('data.0.cash_due_minor', 11500);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 11000)
        ->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Montant encaissé incorrect : 11500 F CFA attendus.');
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 0)->assertUnprocessable();

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 11500)->assertOk();

    $payment = Payment::where('order_id', $ctx['order']->id)->sole();
    expect($payment->status->value)->toBe('succeeded')->and($payment->amount_minor)->toBe(11500)
        ->and($ctx['delivery']->refresh()->cash_collected_minor)->toBe(11500)
        ->and(ledger('571'))->toBe(11500)->and(ledger('411'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

it('does not ask for the code to be guessed from the cash error: the code is checked first', function () {
    $ctx = readyDeliveryOrder('cash_on_delivery');
    $courier = outForDelivery($ctx['delivery']);
    $wrong = $ctx['delivery']->confirmation_code === '000000' ? '111111' : '000000';

    // Un mauvais code ne révèle jamais le montant dû.
    $response = confirm($ctx['delivery'], $courier, $wrong, 1)->assertUnprocessable();

    expect($response->json('errors.delivery.0'))->toContain('Code de confirmation incorrect')->not->toContain('attendus');
});

it('collects the real total of a cash on delivery order after a lighter weighing', function () {
    $ctx = readyDeliveryOrder('cash_on_delivery', 5000, 2); // payé 11 500
    // Préparation : un article en moins => 5 000 de moins, frais conservés (6 500 réellement dus).
    $ctx['order']->update(['final_total_minor' => 6500, 'adjustment_minor' => -5000]);
    app(GenerateCreditNoteAction::class)->execute($ctx['order']->id, 5000, 'Écart de préparation', "adjustment:order:{$ctx['order']->id}");
    $courier = outForDelivery($ctx['delivery']);

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 11500)->assertUnprocessable()->assertJsonPath('errors.delivery.0', 'Montant encaissé incorrect : 6500 F CFA attendus.');
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code, 6500)->assertOk();

    expect(ledger('571'))->toBe(6500)->and(ledger('411'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

// ───────────────────────── échec et retour ─────────────────────────

it('declares a failed delivery with a mandatory reason and forgets the position', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'location', ['latitude' => 5.3, 'longitude' => -4.0])->assertNoContent();

    courierCall($courier, $ctx['delivery'], 'fail', [])->assertUnprocessable();
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'ab'])->assertUnprocessable();
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'Client absent'])->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_reason', 'Client absent');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::DeliveryFailed)->and(DeliveryLocation::count())->toBe(0);
});

it('lets the courier try again after a failure', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'Client absent'])->assertOk();

    courierCall($courier, $ctx['delivery'], 'start')->assertOk()->assertJsonPath('data.status', 'out_for_delivery')->assertJsonPath('data.failure_reason', null);
    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Delivered);
});

it('returns a failed prepaid delivery to the store: stock back, slot freed, customer refunded and credited', function () {
    $ctx = readyDeliveryOrder('mobile_money', 5000, 2);
    $lot = StockLot::where('quantity_reserved', 2)->firstOrFail();
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'Adresse introuvable'])->assertOk();

    $this->actingAs(dispatcher(), 'sanctum')->postJson("/api/v1/delivery/deliveries/{$ctx['delivery']->id}/return", ['reason' => 'Client injoignable'])
        ->assertOk()->assertJsonPath('data.status', 'returned');

    expect(Order::find($ctx['order']->id)->status)->toBe(OrderStatus::Returned)
        ->and($lot->refresh()->quantity_reserved)->toBe(0)->and($lot->quantity_on_hand)->toBe(50)
        ->and($ctx['slot']->refresh()->booked)->toBe(0)
        ->and(refundsOf($ctx['order']->id)->sole()->amount_minor)->toBe(11500)
        ->and(creditNotesOf($ctx['order']->id)->sole()->total_minor)->toBe(11500)
        ->and(ledger('411'))->toBe(0)->and(ledger('5851'))->toBe(0)->and(ledger('701'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

it('returns a failed cash on delivery order with no refund, only a credit note', function () {
    $ctx = readyDeliveryOrder('cash_on_delivery', 5000, 2);
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'Client refuse la commande'])->assertOk();

    $this->actingAs(dispatcher(), 'sanctum')->postJson("/api/v1/delivery/deliveries/{$ctx['delivery']->id}/return", ['reason' => 'Refus client'])->assertOk();

    expect(refundsOf($ctx['order']->id))->toHaveCount(0)
        ->and(creditNotesOf($ctx['order']->id)->sole()->total_minor)->toBe(11500)
        ->and(ledger('411'))->toBe(0)->and(ledger('571'))->toBe(0)->and(Payment::where('order_id', $ctx['order']->id)->sole()->status->value)->toBe('pending_on_delivery');
});

it('only returns a delivery that failed, and only for dispatchers', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    $url = "/api/v1/delivery/deliveries/{$ctx['delivery']->id}/return";

    $this->actingAs(dispatcher(), 'sanctum')->postJson($url, ['reason' => 'Test'])->assertUnprocessable(); // pas en échec
    courierCall($courier, $ctx['delivery'], 'fail', ['reason' => 'Absent'])->assertOk();

    $this->actingAs($courier, 'sanctum')->postJson($url, ['reason' => 'Test'])->assertForbidden();
    $this->actingAs(dispatcher(), 'sanctum')->postJson($url, [])->assertUnprocessable(); // motif obligatoire
    $this->actingAs(dispatcher(), 'sanctum')->postJson($url, ['reason' => 'Test'])->assertOk();
});

it('frees the courier tour once the delivery is over', function () {
    $ctx = readyDeliveryOrder();
    $courier = outForDelivery($ctx['delivery']);
    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-tour')->assertJsonCount(1, 'data');

    confirm($ctx['delivery'], $courier, $ctx['delivery']->confirmation_code)->assertOk();

    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-tour')->assertJsonCount(0, 'data');
});

it('keeps the ledger balanced across prepaid, cash and returned deliveries', function () {
    $paid = readyDeliveryOrder('mobile_money');
    $cash = readyDeliveryOrder('cash_on_delivery');
    $lost = readyDeliveryOrder('mobile_money');

    $c1 = outForDelivery($paid['delivery']);
    confirm($paid['delivery'], $c1, $paid['delivery']->confirmation_code)->assertOk();

    $c2 = outForDelivery($cash['delivery']);
    confirm($cash['delivery'], $c2, $cash['delivery']->confirmation_code, $cash['total'])->assertOk();

    $c3 = outForDelivery($lost['delivery']);
    courierCall($c3, $lost['delivery'], 'fail', ['reason' => 'Absent'])->assertOk();
    $this->actingAs(dispatcher(), 'sanctum')->postJson("/api/v1/delivery/deliveries/{$lost['delivery']->id}/return", ['reason' => 'Abandon'])->assertOk();

    expect(ledgerIsBalanced())->toBeTrue()->and(ledger('411'))->toBe(0)
        ->and(ledger('5851'))->toBe($paid['total'])  // seule la commande livrée en ligne reste encaissée
        ->and(ledger('571'))->toBe($cash['total']);  // et l'argent des espèces est en caisse
});
