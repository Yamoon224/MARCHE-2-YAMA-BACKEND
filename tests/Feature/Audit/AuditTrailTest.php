<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function adminUser(): User
{
    $user = User::factory()->role(Role::Admin)->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('records mutating staff requests with the result and masks secrets', function () {
    $admin = adminUser();

    $this->postJson('/api/v1/staff', [
        'name' => 'Awa', 'email' => 'awa@yama.test', 'role' => 'cashier', 'password' => 'motdepasse1', 'token' => 'abc',
    ])->assertCreated();

    $log = AuditLog::firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->method)->toBe('POST')
        ->and($log->path)->toBe('/api/v1/staff')
        ->and($log->status)->toBe(201)
        ->and($log->hash)->toHaveLength(64)
        ->and($log->prev_hash)->toBeNull();

    $payload = json_decode($log->payload, true);
    expect($payload['password'])->toBe('***')->and($payload['token'])->toBe('***')->and($payload['email'])->toBe('awa@yama.test');
    expect($log->payload)->not->toContain('motdepasse1');
});

it('records failed attempts too', function () {
    adminUser();

    $this->postJson('/api/v1/staff', ['name' => 'X'])->assertUnprocessable();

    expect(AuditLog::firstOrFail()->status)->toBe(422);
});

it('ignores reads, anonymous requests and customers', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/orders', ['items' => []]);
    asGuest();
    $this->postJson('/api/v1/auth/login', ['login' => 'x', 'password' => 'y']);
    adminUser();
    $this->getJson('/api/v1/staff')->assertOk();

    expect(AuditLog::count())->toBe(0);
});

it('chains entries and verifies the chain', function () {
    adminUser();
    $this->postJson('/api/v1/staff', ['name' => 'A']);
    $this->postJson('/api/v1/staff', ['name' => 'B']);
    $this->postJson('/api/v1/staff', ['name' => 'C']);

    $logs = AuditLog::orderBy('id')->get();
    expect($logs[1]->prev_hash)->toBe($logs[0]->hash)->and($logs[2]->prev_hash)->toBe($logs[1]->hash);

    $this->getJson('/api/v1/audit/verify')->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.checked', 3);
});

it('detects tampering with an entry', function () {
    adminUser();
    $this->postJson('/api/v1/staff', ['name' => 'A']);
    $this->postJson('/api/v1/staff', ['name' => 'B']);
    $first = AuditLog::orderBy('id')->firstOrFail();

    // Contourne le trigger MySQL et le modèle, comme le ferait quelqu'un qui modifierait la base directement.
    DB::unprepared('DROP TRIGGER audit_logs_no_update');
    DB::table('audit_logs')->where('id', $first->id)->update(['status' => 200]);
    DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit en ecriture seule'");

    $this->getJson('/api/v1/audit/verify')->assertOk()->assertJsonPath('data.valid', false)->assertJsonPath('data.broken_at_id', $first->id);
});

it('is write-only, in the model and in the database', function () {
    adminUser();
    $this->postJson('/api/v1/staff', ['name' => 'A']);
    $log = AuditLog::firstOrFail();

    expect(fn () => $log->update(['status' => 200]))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['status' => 200]))->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->delete())->toThrow(QueryException::class);
});

it('serves the journal with filters to administrators only', function () {
    $admin = adminUser();
    $this->postJson('/api/v1/staff', ['name' => 'A']);
    $this->postJson('/api/v1/staff', ['name' => 'B', 'email' => 'b@yama.test', 'role' => 'picker', 'password' => 'motdepasse1']);

    $this->getJson('/api/v1/audit')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.method', 'POST');
    $this->getJson('/api/v1/audit?failed=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 422);
    $this->getJson("/api/v1/audit?user_id={$admin->id}&method=POST&path=staff")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/audit?method=DELETE')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/audit?from='.now()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/audit?from=pas-une-date')->assertUnprocessable();

    foreach ([Role::Support, Role::Accountant, Role::Marketing, Role::Customer] as $role) {
        $this->actingAs(User::factory()->role($role)->create(), 'sanctum');
        $this->getJson('/api/v1/audit')->assertForbidden();
        $this->getJson('/api/v1/audit/verify')->assertForbidden();
    }
});
