<?php

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('fills the missing UUIDs and leaves existing ones untouched', function () {
    // La colonne est obligatoire en production : on la rend nullable le temps du test pour simuler d'anciennes lignes.
    Schema::table('orders', fn (Blueprint $t) => $t->uuid('uuid')->nullable()->change());

    $kept = Order::factory()->create();
    $empty = Order::factory()->create();
    DB::table('orders')->where('id', $empty->id)->update(['uuid' => null]);

    $this->artisan('uuid:backfill', ['--dry-run' => true])->expectsOutputToContain('1 ligne(s) sans UUID')->assertSuccessful();
    expect(DB::table('orders')->where('id', $empty->id)->value('uuid'))->toBeNull();

    $this->artisan('uuid:backfill', ['table' => 'orders'])->assertSuccessful();

    expect(DB::table('orders')->where('id', $empty->id)->value('uuid'))->toMatch('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/')
        ->and(DB::table('orders')->where('id', $kept->id)->value('uuid'))->toBe($kept->uuid);
});
