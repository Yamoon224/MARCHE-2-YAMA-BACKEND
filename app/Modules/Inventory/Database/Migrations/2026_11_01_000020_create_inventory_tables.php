<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // product_id : identifiant du module Catalog, volontairement sans clé étrangère (frontière de module).
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedBigInteger('product_id');
            $table->string('lot_code', 50);
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('quantity_on_hand');
            $table->unsignedBigInteger('quantity_reserved')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id', 'lot_code']);
            $table->index(['warehouse_id', 'product_id', 'expires_on']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained('stock_lots');
            $table->string('type', 20);
            $table->bigInteger('quantity'); // signée : + entrée, - sortie
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained('stock_lots');
            $table->string('reference', 60)->index();
            $table->unsignedBigInteger('quantity');
            $table->timestamp('expires_at')->nullable()->index(); // null = maintenue (commande payée)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('warehouses');
    }
};
