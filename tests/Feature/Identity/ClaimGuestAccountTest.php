<?php

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

function guestSession(array $over = []): array
{
    $response = test()->postJson('/api/v1/auth/guest', ['name' => 'Awa Koné', 'email' => 'awa@example.com', 'phone' => '+33612345678', ...$over])->assertCreated();
    app('auth')->forgetGuards();

    return ['id' => $response->json('user.id'), 'token' => $response->json('token')];
}

it('turns a guest into a real account that can log in, keeping the orders', function () {
    $guest = guestSession();
    Order::factory()->create(['user_id' => $guest['id'], 'number' => 'CMD-00000300']);

    $this->withToken($guest['token'])->postJson('/api/v1/auth/claim-account', ['password' => 'un-mot-de-passe'])
        ->assertOk()->assertJsonPath('data.is_guest', false)->assertJsonPath('data.email', 'awa@example.com');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@example.com', 'password' => 'un-mot-de-passe'])
        ->assertOk()->assertJsonPath('user.id', $guest['id']);

    expect(Order::where('number', 'CMD-00000300')->value('user_id'))->toBe($guest['id']);
});

it('refuses when a real account already uses that e-mail and leaves it untouched', function () {
    $owner = User::factory()->create(['email' => 'awa@example.com']);
    $guest = guestSession();

    $this->withToken($guest['token'])->postJson('/api/v1/auth/claim-account', ['password' => 'un-mot-de-passe'])
        ->assertUnprocessable()->assertJsonValidationErrors('account');

    expect(User::find($guest['id'])->is_guest)->toBeTrue()->and($owner->fresh()->email)->toBe('awa@example.com');
});

it('does not fail when the phone number belongs to another account', function () {
    User::factory()->create(['phone' => '+33612345678']);
    $guest = guestSession();

    $this->withToken($guest['token'])->postJson('/api/v1/auth/claim-account', ['password' => 'un-mot-de-passe'])->assertOk();

    expect(User::find($guest['id'])->phone)->toBeNull();
});

it('requires a session, a guest and a long enough password', function () {
    $this->postJson('/api/v1/auth/claim-account', ['password' => 'un-mot-de-passe'])->assertUnauthorized();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/auth/claim-account', ['password' => 'un-mot-de-passe'])->assertUnprocessable();

    $this->app['auth']->forgetGuards();
    $guest = guestSession();
    $this->withToken($guest['token'])->postJson('/api/v1/auth/claim-account', ['password' => 'court'])->assertUnprocessable()->assertJsonValidationErrors('password');
});
