<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cashier_id / order_id : identifiants d'autres modules, sans clé étrangère (frontière de module).
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cashier_id')->index();
            $table->unsignedBigInteger('opening_float_minor');
            $table->unsignedBigInteger('expected_cash_minor')->nullable();
            $table->unsignedBigInteger('counted_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable(); // compté - attendu : négatif = manquant
            $table->string('note')->nullable();
            // 1 tant que la caisse est ouverte, NULL une fois fermée : l'unicité (caissier, 1) interdit deux caisses ouvertes.
            $table->unsignedTinyInteger('open_key')->nullable()->default(1);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->unique(['cashier_id', 'open_key']);
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions');
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('order_number', 30);
            $table->string('tender', 15); // cash | mobile_money | card
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('tendered_minor');
            $table->unsignedBigInteger('change_minor')->default(0);
            $table->json('lines'); // ticket réimprimable tel qu'il a été remis
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pos_session_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_sessions');
    }
};
