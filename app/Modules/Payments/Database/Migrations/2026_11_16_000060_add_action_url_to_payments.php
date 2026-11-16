<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adresse de la page de paiement hébergée (Stripe Checkout) : permet de reprendre un paiement interrompu. */
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->string('action_url', 2048)->nullable()->after('provider_reference'));
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('action_url'));
    }
};
