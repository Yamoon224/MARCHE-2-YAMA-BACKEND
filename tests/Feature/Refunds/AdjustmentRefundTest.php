<?php

use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Enums\RefundKind;
use App\Modules\Payments\Events\RefundIssued;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Event;

/** Prépare la commande, pèse `$grams` et termine. Retourne le contexte. */
function weighedOrder(int $orderedGrams, int $servedGrams, int $vatBp = 0): array
{
    $tomatoes = weightProduct(900);
    $tomatoes->update(['vat_rate_bp' => $vatBp]);

    $ctx = orderInPreparation([['product' => $tomatoes, 'quantity' => $orderedGrams]]);
    $picker = staffUser(Role::Picker);

    test()->actingAs($picker, 'sanctum')->postJson(pickUrl($ctx['list'], '/start'))->assertOk();
    test()->actingAs($picker, 'sanctum')->postJson(lineUrl($ctx['list'], $ctx['list']->lines->first()->id, 'pick'), ['quantity' => $servedGrams])->assertOk();
    test()->actingAs($picker, 'sanctum')->postJson(pickUrl($ctx['list'], '/complete'))->assertOk();

    return $ctx;
}

it('refunds the weighing difference to the original payment method', function () {
    $ctx = weighedOrder(1500, 1200); // 1 350 payés, 1 080 dus : 270 à rendre

    $refund = refundsOf($ctx['order']->id)->sole();
    expect($refund->amount_minor)->toBe(270)->and($refund->kind)->toBe(RefundKind::Adjustment)->and($refund->status->value)->toBe('succeeded');
    expect($refund->payment->method->value)->toBe('mobile_money');
});

it('credits the invoice for the difference, with the VAT spread pro rata', function () {
    $ctx = weighedOrder(1500, 1200, 1800);

    // Facture : 1 350 TTC, TVA 206 (1 350 - 1 144 HT). Avoir de 270 : TVA 206 × 270 / 1 350 = 41.
    $note = creditNotesOf($ctx['order']->id)->sole();
    expect($note->total_minor)->toBe(270)->and($note->vat_minor)->toBe(41)->and($note->net_minor)->toBe(229)
        ->and($note->reason)->toBe('Écart de préparation');
});

it('leaves the ledger consistent: the customer account is cleared and the kept sale is what remains', function () {
    weighedOrder(1500, 1200, 1800);

    // Reste en vente : 1 080 TTC => 5851 (encaissé net) 1 080 ; 411 soldé ; équilibre global.
    expect(ledger('5851'))->toBe(1080)->and(ledger('411'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
    expect(-ledger('701') + -ledger('4431'))->toBe(1080); // HT + TVA conservés = montant réel
});

it('does not refund when the order is served as paid, or when the customer owes more', function () {
    $exact = weighedOrder(1000, 1000);
    $heavier = weighedOrder(1000, 1100); // +10 % : le client doit 90 de plus, rien à rendre

    expect(refundsOf($exact['order']->id))->toHaveCount(0)->and(refundsOf($heavier['order']->id))->toHaveCount(0)
        ->and(creditNotesOf($heavier['order']->id))->toHaveCount(0)
        ->and(Order::find($heavier['order']->id)->adjustment_minor)->toBe(90);
});

it('refunds an article that is entirely out of stock', function () {
    $ctx = weighedOrder(1000, 0); // plus rien de servi : tout est à rendre

    expect(refundsOf($ctx['order']->id)->sole()->amount_minor)->toBe(900)
        ->and(creditNotesOf($ctx['order']->id)->sole()->total_minor)->toBe(900);
});

it('keeps the delivery fee when an article is only half served', function () {
    $zone = DeliveryZone::factory()->create(['fee_minor' => 1500, 'free_above_minor' => null, 'min_order_minor' => 0]);
    $slot = DeliverySlot::factory()->create(['delivery_zone_id' => $zone->id]);
    $tomatoes = weightProduct(900);
    $customer = User::factory()->create();

    $order = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $tomatoes->id, 'quantity' => 1000]], // 900
        'fulfillment' => 'delivery',
        'delivery' => ['zone_id' => $zone->id, 'slot_id' => $slot->id, 'phone' => '+2250700112233', 'address' => ['neighborhood' => 'Cocody']],
    ])->assertCreated();
    expect($order->json('data.total_minor'))->toBe(2400); // 900 + 1 500 de livraison

    $orderId = internalOrderId($order->json('data.id'));
    $paymentId = startPayment($customer, $orderId)->json('data.id');
    sendWebhook(webhookFor(Payment::findOrFail($paymentId)->provider_reference, 'succeeded', 2400, 'evt-fee'))->assertOk();
    $transition = app(TransitionOrderAction::class);
    $transition->execute(Order::findOrFail($orderId), OrderStatus::Confirmed, null);
    $transition->execute(Order::findOrFail($orderId), OrderStatus::Preparing, null);

    $list = PickList::where('order_id', $orderId)->firstOrFail();
    $picker = staffUser(Role::Picker);
    test()->actingAs($picker, 'sanctum')->postJson(pickUrl($list, '/start'))->assertOk();
    test()->actingAs($picker, 'sanctum')->postJson(lineUrl($list, $list->lines->first()->id, 'pick'), ['quantity' => 500])->assertOk();
    test()->actingAs($picker, 'sanctum')->postJson(pickUrl($list, '/complete'))->assertOk()->assertJsonPath('adjustment_minor', -450);

    // Seul l'article est rendu (450) ; les 1 500 de livraison restent acquis.
    expect(refundsOf($orderId)->sole()->amount_minor)->toBe(450)
        ->and(Order::find($orderId)->final_total_minor)->toBe(1950);
});

it('issues one refund event per refund', function () {
    Event::fake([RefundIssued::class]);

    weighedOrder(1500, 1200);

    Event::assertDispatchedTimes(RefundIssued::class, 1);
    Event::assertDispatched(RefundIssued::class, fn ($e) => $e->amountMinor === 270 && $e->kind === RefundKind::Adjustment);
});
