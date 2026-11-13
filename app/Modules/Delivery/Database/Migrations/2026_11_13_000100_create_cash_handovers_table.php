<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // courier_id / received_by : identifiants du module Identity, sans clé étrangère (frontière de module).
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('courier_id')->index();
            $table->unsignedBigInteger('received_by');
            $table->unsignedBigInteger('expected_minor');
            $table->unsignedBigInteger('counted_minor');
            $table->bigInteger('variance_minor'); // compté - attendu : négatif = manquant
            $table->unsignedSmallInteger('deliveries_count');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('cash_handover_id')->nullable()->after('cash_collected_minor')->constrained('cash_handovers');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_handover_id');
        });
        Schema::dropIfExists('cash_handovers');
    }
};
