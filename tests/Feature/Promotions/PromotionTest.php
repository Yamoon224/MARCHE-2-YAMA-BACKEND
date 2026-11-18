<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Promotions\Models\Promotion;

function marketing(): User
{
    $user = User::factory()->role(Role::Marketing)->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('lets marketing and admin manage codes, nobody else', function () {
    foreach ([Role::Customer, Role::Support, Role::Accountant] as $role) {
        $this->actingAs(User::factory()->role($role)->create(), 'sanctum')->getJson('/api/v1/promotions')->assertForbidden();
    }
    asGuest();
    $this->getJson('/api/v1/promotions')->assertUnauthorized();

    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')->getJson('/api/v1/promotions')->assertOk();
    marketing();
    $this->getJson('/api/v1/promotions')->assertOk();
});

it('creates a code, normalised to upper case, with its computed state', function () {
    marketing();

    $this->postJson('/api/v1/promotions', ['code' => ' bienvenue10 ', 'type' => 'percent', 'value' => 10, 'min_order_minor' => 2000])
        ->assertCreated()->assertJsonPath('data.code', 'BIENVENUE10')->assertJsonPath('data.state', 'active')->assertJsonPath('data.used_count', 0);

    $this->postJson('/api/v1/promotions', ['code' => 'BIENVENUE10', 'type' => 'fixed', 'value' => 500])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('rejects invalid definitions', function () {
    marketing();

    $this->postJson('/api/v1/promotions', ['code' => 'TROP', 'type' => 'percent', 'value' => 150])->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->postJson('/api/v1/promotions', ['code' => 'BAD CODE', 'type' => 'fixed', 'value' => 100])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/promotions', ['code' => 'DATES', 'type' => 'fixed', 'value' => 100, 'starts_at' => '2026-12-10', 'ends_at' => '2026-12-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
});

it('computes the state: active, scheduled, expired, exhausted, disabled', function () {
    $active = Promotion::factory()->create();
    $scheduled = Promotion::factory()->create(['starts_at' => now()->addDays(2)]);
    $expired = Promotion::factory()->create(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);
    $exhausted = Promotion::factory()->create(['max_uses' => 3, 'used_count' => 3]);
    $disabled = Promotion::factory()->create(['is_active' => false]);

    expect($active->state()->value)->toBe('active')
        ->and($scheduled->state()->value)->toBe('scheduled')
        ->and($expired->state()->value)->toBe('expired')
        ->and($exhausted->state()->value)->toBe('exhausted')
        ->and($disabled->state()->value)->toBe('disabled');

    marketing();
    foreach (['active' => $active, 'scheduled' => $scheduled, 'expired' => $expired, 'exhausted' => $exhausted, 'disabled' => $disabled] as $state => $promotion) {
        $ids = collect($this->getJson("/api/v1/promotions?state={$state}")->assertOk()->json('data'))->pluck('id')->all();
        expect($ids)->toBe([$promotion->id]);
    }
});

it('updates, deactivates and reactivates a code', function () {
    $promotion = Promotion::factory()->create();
    marketing();

    $this->putJson("/api/v1/promotions/{$promotion->id}", ['value' => 25])->assertOk()->assertJsonPath('data.value', 25);
    $this->postJson("/api/v1/promotions/{$promotion->id}/deactivate")->assertOk()->assertJsonPath('data.state', 'disabled');
    $this->postJson("/api/v1/promotions/{$promotion->id}/activate")->assertOk()->assertJsonPath('data.state', 'active');
});

it('only deletes a code that was never used', function () {
    $fresh = Promotion::factory()->create();
    $used = Promotion::factory()->create(['used_count' => 1]);
    marketing();

    $this->deleteJson("/api/v1/promotions/{$fresh->id}")->assertNoContent();
    $this->deleteJson("/api/v1/promotions/{$used->id}")->assertUnprocessable();
    expect(Promotion::count())->toBe(1);
});

it('validates a code for any signed-in customer and computes the discount', function () {
    Promotion::factory()->create(['code' => 'DIX', 'type' => 'percent', 'value' => 10, 'min_order_minor' => 2000]);
    Promotion::factory()->create(['code' => 'CINQ', 'type' => 'fixed', 'value' => 500]);
    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->postJson('/api/v1/promotions/validate', ['code' => 'dix', 'subtotal' => 5000])
        ->assertOk()->assertJsonPath('data.discount_minor', 500)->assertJsonPath('data.total_after_discount_minor', 4500);
    // Une remise fixe ne dépasse jamais le sous-total.
    $this->postJson('/api/v1/promotions/validate', ['code' => 'CINQ', 'subtotal' => 300])
        ->assertOk()->assertJsonPath('data.discount_minor', 300)->assertJsonPath('data.total_after_discount_minor', 0);
});

it('explains why a code is refused', function () {
    Promotion::factory()->create(['code' => 'MIN', 'min_order_minor' => 2000]);
    Promotion::factory()->create(['code' => 'OLD', 'ends_at' => now()->subDay()]);
    Promotion::factory()->create(['code' => 'FULL', 'max_uses' => 1, 'used_count' => 1]);
    Promotion::factory()->create(['code' => 'OFF', 'is_active' => false]);
    $this->actingAs(User::factory()->create(), 'sanctum');

    foreach (['MIN' => 'minimum', 'OLD' => 'expiré', 'FULL' => 'maximal', 'OFF' => 'pas actif', 'NOPE' => "n'existe pas"] as $code => $fragment) {
        $response = $this->postJson('/api/v1/promotions/validate', ['code' => $code, 'subtotal' => 1000])->assertUnprocessable();

        expect($response->json('errors.code.0'))->toContain($fragment);
    }

    asGuest();
    $this->postJson('/api/v1/promotions/validate', ['code' => 'MIN', 'subtotal' => 5000])->assertUnauthorized();
});

it('does not consume a use when validating', function () {
    $promotion = Promotion::factory()->create(['max_uses' => 1]);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/promotions/validate', ['code' => $promotion->code, 'subtotal' => 1000])->assertOk();

    expect($promotion->fresh()->used_count)->toBe(0);
});
