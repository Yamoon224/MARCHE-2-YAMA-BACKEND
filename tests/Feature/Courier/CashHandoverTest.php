<?php

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/** Livraison payée en espèces, remise au client par ce livreur. @return array{delivery: Delivery, total: int} */
function cashDeliveredBy(User $courier, int $price = 5000, int $quantity = 2): array
{
    $ctx = readyDeliveryOrder('cash_on_delivery', $price, $quantity);
    assignCourier($ctx['delivery'], $courier)->assertOk();
    courierCall($courier, $ctx['delivery'], 'start')->assertOk();
    courierCall($courier, $ctx['delivery'], 'confirm', ['code' => $ctx['delivery']->confirmation_code, 'cash_collected_minor' => $ctx['total']])->assertOk();

    return ['delivery' => $ctx['delivery']->refresh(), 'total' => $ctx['total']];
}

function handOver(User $by, User $courier, int $counted, ?string $note = null)
{
    return test()->actingAs($by, 'sanctum')->postJson('/api/v1/delivery/cash/handovers', array_filter(
        ['courier_id' => $courier->id, 'counted_minor' => $counted, 'note' => $note], fn ($v) => $v !== null,
    ));
}

it('keeps the cash on the courier account until it is handed over', function () {
    $courier = courierUser();
    $first = cashDeliveredBy($courier);
    $second = cashDeliveredBy($courier, 3000, 1);
    $held = $first['total'] + $second['total'];

    expect(ledger('5715'))->toBe($held)->and(ledger('571'))->toBe(0);

    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-cash')
        ->assertOk()->assertJsonPath('data.held_minor', $held)->assertJsonPath('data.deliveries_count', 2);

    $this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/cash')
        ->assertOk()->assertJsonPath('data.0.courier_id', $courier->id)->assertJsonPath('data.0.held_minor', $held);
});

it('settles everything held by the courier into the till when the count is exact', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'] + cashDeliveredBy($courier, 3000, 1)['total'];

    handOver(dispatcher(), $courier, $held)->assertCreated()
        ->assertJsonPath('data.expected_minor', $held)->assertJsonPath('data.variance_minor', 0)->assertJsonPath('data.deliveries_count', 2);

    expect(ledger('5715'))->toBe(0)->and(ledger('571'))->toBe($held)->and(ledger('658'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-cash')->assertJsonPath('data.held_minor', 0);

    // Une seconde remise n'a plus rien à solder : jamais deux fois le même argent.
    handOver(dispatcher(), $courier, $held)->assertUnprocessable()->assertJsonPath('errors.cash.0', "Ce livreur n'a aucune espèce à remettre.");
});

it('books a shortage as a loss and demands an explanation', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'];

    handOver(dispatcher(), $courier, $held - 500)->assertUnprocessable();
    expect(ledger('5715'))->toBe($held); // rien n'a été enregistré

    handOver(dispatcher(), $courier, $held - 500, 'Monnaie rendue en trop')->assertCreated()->assertJsonPath('data.variance_minor', -500);

    expect(ledger('5715'))->toBe(0)->and(ledger('571'))->toBe($held - 500)->and(ledger('658'))->toBe(500)->and(ledgerIsBalanced())->toBeTrue();
});

it('books a surplus as income', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'];

    handOver(dispatcher(), $courier, $held + 200, 'Pourboire laissé dans le sac')->assertCreated()->assertJsonPath('data.variance_minor', 200);

    expect(ledger('571'))->toBe($held + 200)->and(ledger('758'))->toBe(-200)->and(ledgerIsBalanced())->toBeTrue();
});

it('records a handover of nothing counted as a full loss', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'];

    handOver(dispatcher(), $courier, 0, 'Sac volé')->assertCreated();

    expect(ledger('571'))->toBe(0)->and(ledger('658'))->toBe($held)->and(ledger('5715'))->toBe(0)->and(ledgerIsBalanced())->toBeTrue();
});

it('only settles the cash of the requested courier', function () {
    $mine = courierUser('A');
    $other = courierUser('B');
    $myCash = cashDeliveredBy($mine)['total'];
    $otherCash = cashDeliveredBy($other, 3000, 1)['total'];

    handOver(dispatcher(), $mine, $myCash)->assertCreated();

    expect(ledger('5715'))->toBe($otherCash);
    $this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/cash')->assertJsonCount(1, 'data')->assertJsonPath('data.0.courier_id', $other->id);
});

it('does not count cash twice for a delivery paid online', function () {
    $courier = courierUser();
    $ctx = readyDeliveryOrder('mobile_money');
    assignCourier($ctx['delivery'], $courier)->assertOk();
    courierCall($courier, $ctx['delivery'], 'start')->assertOk();
    courierCall($courier, $ctx['delivery'], 'confirm', ['code' => $ctx['delivery']->confirmation_code, 'cash_collected_minor' => 0])->assertOk();

    handOver(dispatcher(), $courier, 0)->assertUnprocessable();
    $this->actingAs($courier, 'sanctum')->getJson('/api/v1/delivery/my-cash')->assertJsonPath('data.held_minor', 0);
});

it('is reserved to staff allowed to receive cash and refuses a non courier', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'];

    handOver($courier, $courier, $held)->assertForbidden(); // le livreur ne se réceptionne pas lui-même
    handOver(User::factory()->role(Role::Picker)->create(), $courier, $held)->assertForbidden();
    handOver(User::factory()->create(), $courier, $held)->assertForbidden();
    asGuest();
    $this->postJson('/api/v1/delivery/cash/handovers', ['courier_id' => $courier->id, 'counted_minor' => $held])->assertUnauthorized();
    $this->actingAs(User::factory()->role(Role::Support)->create(), 'sanctum')->getJson('/api/v1/delivery/cash')->assertForbidden();

    handOver(dispatcher(), User::factory()->create(), 100)->assertUnprocessable()->assertJsonPath('errors.cash.0', "Ce compte n'est pas un livreur.");
    expect(ledger('5715'))->toBe($held);
});

it('validates the counted amount', function () {
    $courier = courierUser();
    cashDeliveredBy($courier);

    foreach ([-1, 'abc', null, 1.5] as $bad) {
        $this->actingAs(dispatcher(), 'sanctum')->postJson('/api/v1/delivery/cash/handovers', ['courier_id' => $courier->id, 'counted_minor' => $bad])->assertUnprocessable();
    }
});

it('lists past handovers', function () {
    $courier = courierUser();
    $held = cashDeliveredBy($courier)['total'];
    handOver(dispatcher(), $courier, $held)->assertCreated();

    $this->actingAs(dispatcher(), 'sanctum')->getJson('/api/v1/delivery/cash/handovers')
        ->assertOk()->assertJsonPath('data.0.counted_minor', $held)->assertJsonPath('data.0.courier_id', $courier->id);
});
