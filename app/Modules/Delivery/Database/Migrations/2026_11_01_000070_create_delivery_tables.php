<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('fee_minor');
            $table->unsignedBigInteger('free_above_minor')->nullable(); // frais offerts au-delà de ce montant
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_zone_id')->constrained('delivery_zones');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked')->default(0);
            $table->timestamps();

            $table->index(['delivery_zone_id', 'starts_at']);
        });

        // order_id : identifiant du module Orders, sans clé étrangère (frontière de module).
        Schema::create('slot_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_slot_id')->constrained('delivery_slots');
            $table->unsignedBigInteger('order_id')->unique();
            $table->timestamps();
        });

        // order_id / courier_id : identifiants d'autres modules, sans clé étrangère (frontière de module).
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('courier_id')->nullable()->index();
            $table->string('status', 20)->index(); // pending | assigned | out_for_delivery | delivered | failed | returned
            $table->text('confirmation_code');      // chiffré : le client le voit, le livreur le saisit
            $table->unsignedSmallInteger('code_attempts')->default(0);
            $table->unsignedBigInteger('cash_collected_minor')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // Dernière position seulement, et supprimée à la fin de la livraison (protection de la géolocalisation).
        Schema::create('delivery_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('deliveries')->cascadeOnDelete();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('slot_bookings');
        Schema::dropIfExists('delivery_slots');
        Schema::dropIfExists('delivery_zones');
    }
};
