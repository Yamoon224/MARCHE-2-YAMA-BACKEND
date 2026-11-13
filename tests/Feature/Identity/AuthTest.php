<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

it('registers a customer and returns a token', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Awa Koné', 'email' => 'awa@example.com', 'password' => 'secret1234',
    ])->assertCreated()->assertJsonPath('user.role', 'customer')->assertJsonStructure(['token']);

    expect(User::where('email', 'awa@example.com')->exists())->toBeTrue();
});

it('requires an email or a phone to register', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'X', 'password' => 'secret1234'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
});

it('logs in with phone or email', function () {
    $user = User::factory()->create(['phone' => '+2250700000001', 'password' => 'secret1234']);

    $this->postJson('/api/v1/auth/login', ['login' => '+2250700000001', 'password' => 'secret1234'])->assertOk();
    $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'secret1234'])->assertOk();
});

it('rejects wrong credentials', function () {
    User::factory()->create(['email' => 'a@b.test', 'password' => 'secret1234']);

    $this->postJson('/api/v1/auth/login', ['login' => 'a@b.test', 'password' => 'nope'])->assertUnprocessable();
});

it('returns the current user when authenticated only', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    $this->actingAs(User::factory()->role(Role::Picker)->create(), 'sanctum')
        ->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role', 'picker');
});
