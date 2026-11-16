<?php

use App\Modules\Identity\Models\User;

function guestPayload(array $over = []): array
{
    return ['name' => 'Awa Koné', 'email' => 'awa@example.com', 'phone' => '+33 6 12 34 56 78', ...$over];
}

it('opens a guest session without an account', function () {
    $response = $this->postJson('/api/v1/auth/guest', guestPayload())
        ->assertCreated()->assertJsonPath('user.is_guest', true)->assertJsonPath('user.role', 'customer');

    $user = User::findOrFail($response->json('user.id'));
    expect($user->contact_email)->toBe('awa@example.com')->and($user->contact_phone)->toBe('+33612345678')
        ->and($user->email)->toBeNull()->and($user->phone)->toBeNull();

    $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.is_guest', true);
});

it('never reuses or touches an existing account with the same e-mail', function () {
    $owner = User::factory()->create(['email' => 'awa@example.com']);

    $guest = $this->postJson('/api/v1/auth/guest', guestPayload())->assertCreated();

    expect($guest->json('user.id'))->not->toBe($owner->id)->and(User::where('email', 'awa@example.com')->count())->toBe(1);
});

it('cannot log in as a guest afterwards', function () {
    $this->postJson('/api/v1/auth/guest', guestPayload())->assertCreated();

    $this->postJson('/api/v1/auth/login', ['login' => 'awa@example.com', 'password' => 'password'])->assertUnprocessable();
});

it('validates the guest contact details', function () {
    $this->postJson('/api/v1/auth/guest', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone']);
    $this->postJson('/api/v1/auth/guest', guestPayload(['email' => 'pas-un-email']))->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/guest', guestPayload(['phone' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('keeps a guest away from other customers orders', function () {
    ['orderId' => $orderId] = awaitingPaymentOrder();
    $guestToken = $this->postJson('/api/v1/auth/guest', guestPayload())->json('token');
    $this->app['auth']->forgetGuards();

    $this->withToken($guestToken)->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($guestToken)->getJson("/api/v1/orders/{$orderId}")->assertForbidden();
});