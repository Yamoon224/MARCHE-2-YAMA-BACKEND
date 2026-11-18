<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

function asRole(Role $role): User
{
    $user = User::factory()->role($role)->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('lists only staff for an administrator, with search and pagination', function () {
    User::factory()->role(Role::Picker)->create(['name' => 'Moussa Preparateur']);
    User::factory()->create(['name' => 'Cliente Lambda']);
    asRole(Role::Admin);

    $this->getJson('/api/v1/staff')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data' => [['id', 'role_label', 'is_active']], 'meta' => ['total']]);
    $this->getJson('/api/v1/staff?q=Moussa')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'picker');
    $this->getJson('/api/v1/staff?role=admin')->assertOk()->assertJsonCount(1, 'data');
});

it('refuses staff management to every non-administrator', function () {
    foreach ([Role::Customer, Role::Support, Role::Marketing, Role::OrderOperator] as $role) {
        asRole($role);
        $this->getJson('/api/v1/staff')->assertForbidden();
        $this->postJson('/api/v1/staff', [])->assertForbidden();
        $this->getJson('/api/v1/roles')->assertForbidden();
        asGuest();
    }

    $this->getJson('/api/v1/staff')->assertUnauthorized();
});

it('creates a staff member who can then sign in', function () {
    asRole(Role::Admin);

    $this->postJson('/api/v1/staff', [
        'name' => 'Awa Caisse', 'email' => 'awa@yama.test', 'phone' => '+33611223344', 'role' => 'cashier', 'password' => 'motdepasse1',
    ])->assertCreated()->assertJsonPath('data.role', 'cashier')->assertJsonPath('data.role_label', 'Caissier')->assertJsonPath('data.is_active', true);

    asGuest();
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@yama.test', 'password' => 'motdepasse1'])->assertOk()->assertJsonPath('user.role', 'cashier');
});

it('never creates a customer or an unknown role through the staff endpoint', function () {
    asRole(Role::Admin);
    $base = ['name' => 'X', 'email' => 'x@yama.test', 'password' => 'motdepasse1'];

    $this->postJson('/api/v1/staff', $base + ['role' => 'customer'])->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->postJson('/api/v1/staff', $base + ['role' => 'root'])->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('updates a member and changes the role', function () {
    $member = User::factory()->role(Role::Picker)->create();
    asRole(Role::Admin);

    $this->putJson("/api/v1/staff/{$member->id}", ['name' => 'Nouveau Nom', 'role' => 'stock_keeper'])
        ->assertOk()->assertJsonPath('data.name', 'Nouveau Nom')->assertJsonPath('data.role', 'stock_keeper');
});

it('cannot change its own role', function () {
    $admin = asRole(Role::Admin);

    $this->putJson("/api/v1/staff/{$admin->id}", ['role' => 'support'])->assertUnprocessable()->assertJsonValidationErrors('role');
    expect($admin->fresh()->role)->toBe(Role::Admin);
});

it('deactivates an account: tokens revoked, sign-in refused, then reactivation', function () {
    $member = User::factory()->role(Role::Support)->create(['email' => 'sup@yama.test', 'password' => 'motdepasse1']);
    $member->createToken('api');
    asRole(Role::Admin);

    $this->deleteJson("/api/v1/staff/{$member->id}")->assertOk()->assertJsonPath('data.is_active', false);

    expect($member->tokens()->count())->toBe(0);
    asGuest();
    $this->postJson('/api/v1/auth/login', ['login' => 'sup@yama.test', 'password' => 'motdepasse1'])
        ->assertUnprocessable()->assertJsonValidationErrors('login');

    $admin = User::factory()->role(Role::Admin)->create();
    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/staff/{$member->id}/reactivate")->assertOk()->assertJsonPath('data.is_active', true);
    asGuest();
    $this->postJson('/api/v1/auth/login', ['login' => 'sup@yama.test', 'password' => 'motdepasse1'])->assertOk();
});

it('cannot deactivate its own account', function () {
    $admin = asRole(Role::Admin);

    $this->deleteJson("/api/v1/staff/{$admin->id}")->assertUnprocessable()->assertJsonValidationErrors('user');
    expect($admin->fresh()->isActive())->toBeTrue();
});

it('does not expose customers as staff', function () {
    $customer = User::factory()->create();
    asRole(Role::Admin);

    $this->getJson("/api/v1/staff/{$customer->id}")->assertNotFound();
    $this->deleteJson("/api/v1/staff/{$customer->id}")->assertNotFound();
});

it('lists the roles that can be given to staff with French labels and permissions', function () {
    asRole(Role::Admin);

    $response = $this->getJson('/api/v1/roles')->assertOk();
    $roles = collect($response->json('data'))->keyBy('value');

    expect($roles)->not->toHaveKey('customer')
        ->and($roles['admin']['permissions'])->toBe(['*'])
        ->and($roles['marketing']['label'])->toBe('Marketing')
        ->and($roles['marketing']['permissions'])->toContain('promotions.manage');
});

it('lists customers with orders count and total spent for authorised staff', function () {
    $customer = User::factory()->create(['name' => 'Fatou Client']);
    User::factory()->create(['name' => 'Autre Client']);
    Order::factory()->forUser($customer->id)->status(OrderStatus::Delivered)->create(['total_minor' => 4000]);
    Order::factory()->forUser($customer->id)->status(OrderStatus::Completed)->create(['total_minor' => 6000, 'final_total_minor' => 5500]);
    Order::factory()->forUser($customer->id)->status(OrderStatus::Cancelled)->create(['total_minor' => 9000]);
    asRole(Role::Support);

    $row = collect($this->getJson('/api/v1/customers?q=Fatou')->assertOk()->assertJsonCount(1, 'data')->json('data'))->first();
    expect($row['orders_count'])->toBe(2)->and($row['total_spent_minor'])->toBe(9500);

    $this->getJson("/api/v1/customers/{$customer->id}")->assertOk()->assertJsonPath('data.orders_count', 2);
    $this->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 2);
});

it('keeps customers away from other roles and staff out of the customer list', function () {
    $staff = User::factory()->role(Role::Picker)->create();
    asRole(Role::Picker);
    $this->getJson('/api/v1/customers')->assertForbidden();

    asRole(Role::Marketing);
    $this->getJson('/api/v1/customers')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/customers/{$staff->id}")->assertNotFound();
});
