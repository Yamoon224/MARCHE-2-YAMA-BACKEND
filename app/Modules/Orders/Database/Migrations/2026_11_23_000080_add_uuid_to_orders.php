<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->uuid('uuid')->nullable()->unique()->after('id'));

        // Commandes existantes : un UUID chacune, puis la colonne devient obligatoire.
        DB::table('orders')->whereNull('uuid')->orderBy('id')->each(
            fn ($order) => DB::table('orders')->where('id', $order->id)->update(['uuid' => (string) Str::uuid()])
        );

        Schema::table('orders', fn (Blueprint $t) => $t->uuid('uuid')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('uuid'));
    }
};
