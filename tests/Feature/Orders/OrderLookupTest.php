<?php

use App\Modules\Identity\Actions\CreateGuestCustomerAction;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

function guestOrder(string $email = 'awa@example.com', string $number = 'CMD-00000099'): Order
{
    $guest = app(CreateGuestCustomerAction::class)->execute(['name' => 'Awa Koné', 'email' => $email, 'phone' => '+33612345678']);

    return Order::factory()->create(['user_id' => $guest->id, 'number' => $number]);
}

it('lets a guest follow their order with the number and the e-mail, without any session', function () {
    guestOrder();

    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000099', 'email' => 'awa@example.com'])
        ->assertOk()->assertJsonPath('data.number', 'CMD-00000099')->assertJsonStructure(['data' => ['status', 'lines', 'history', 'total_minor']]);
});

it('ignores e-mail case and surrounding spaces', function () {
    guestOrder();

    $this->postJson('/api/v1/orders/lookup', ['number' => ' CMD-00000099 ', 'email' => ' AWA@Example.COM '])->assertOk();
});

it('also works for a registered customer using the account e-mail', function () {
    $customer = User::factory()->create(['email' => 'client@example.com']);
    Order::factory()->create(['user_id' => $customer->id, 'number' => 'CMD-00000100']);

    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000100', 'email' => 'client@example.com'])->assertOk();
});

it('answers identically for a wrong e-mail and an unknown number', function () {
    guestOrder();

    $wrongEmail = $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000099', 'email' => 'autre@example.com']);
    $unknown = $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-99999999', 'email' => 'awa@example.com']);

    $wrongEmail->assertNotFound();
    $unknown->assertNotFound();
    expect($wrongEmail->json('message'))->toBe($unknown->json('message'));
});

it('never matches an order whose owner has no e-mail', function () {
    $customer = User::factory()->create(['email' => null]);
    Order::factory()->create(['user_id' => $customer->id, 'number' => 'CMD-00000101']);

    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000101', 'email' => ''])->assertUnprocessable();
    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000101', 'email' => 'x@example.com'])->assertNotFound();
});

it('does not expose internal staff identifiers in the public history', function () {
    $order = guestOrder();
    $order->history()->create(['from_status' => 'paid', 'to_status' => 'confirmed', 'user_id' => 42]);

    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000099', 'email' => 'awa@example.com'])
        ->assertOk()->assertJsonPath('data.history.0.user_id', null);
});

it('throttles repeated guesses', function () {
    guestOrder();

    foreach (range(1, 8) as $i) {
        $this->postJson('/api/v1/orders/lookup', ['number' => "CMD-0000{$i}", 'email' => 'awa@example.com'])->assertNotFound();
    }

    $this->postJson('/api/v1/orders/lookup', ['number' => 'CMD-00000099', 'email' => 'awa@example.com'])->assertTooManyRequests();
});
