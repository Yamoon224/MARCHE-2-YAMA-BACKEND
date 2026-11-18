<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

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

describe('forgotten password', function () {
    it('sends a reset link to a staff account, pointing at the back office', function () {
        Notification::fake();
        $user = User::factory()->role(Role::Picker)->create(['email' => 'picker@yama.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'picker@yama.test'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $url = $notification->url($user);

            return str_starts_with($url, rtrim(config('app.backoffice_url'), '/').'/reset-password?token=')
                && str_contains($url, 'email=picker%40yama.test');
        });
    });

    it('answers the same and sends nothing for an unknown address or a customer', function () {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'client@yama.test']);

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@yama.test']);
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'client@yama.test']);

        $unknown->assertOk();
        expect($known->json())->toBe($unknown->json());
        Notification::assertNothingSent();
    });

    it('requires a valid e-mail address', function () {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('email');
    });

    it('changes the password with a valid token, once, and signs out other sessions', function () {
        $user = User::factory()->role(Role::Support)->create(['email' => 's@yama.test', 'password' => 'oldpass123']);
        $user->createToken('api');
        $token = Password::broker()->createToken($user);
        $payload = ['token' => $token, 'email' => 's@yama.test', 'password' => 'newpass456', 'password_confirmation' => 'newpass456'];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();

        expect(Hash::check('newpass456', $user->fresh()->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(0);
        $this->postJson('/api/v1/auth/login', ['login' => 's@yama.test', 'password' => 'newpass456'])->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
    });

    it('rejects a wrong token and a weak password', function () {
        $user = User::factory()->role(Role::Support)->create(['email' => 's@yama.test']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', ['token' => 'wrong', 'email' => 's@yama.test', 'password' => 'newpass456', 'password_confirmation' => 'newpass456'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 's@yama.test', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    });
});
