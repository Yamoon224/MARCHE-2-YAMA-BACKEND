<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id / product_id : identifiants d'autres modules, sans clé étrangère (frontière de module).
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 30)->index();
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            // Montant réel après préparation ; adjustment_minor = final - total (négatif : à rembourser).
            $table->unsignedBigInteger('final_total_minor')->nullable();
            $table->bigInteger('adjustment_minor')->default(0);
            $table->string('channel', 20)->default('web');
            $table->string('fulfillment', 20)->default('pickup'); // pickup | delivery
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->unsignedBigInteger('delivery_slot_id')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 40);
            $table->string('name');
            $table->string('sale_unit', 10);
            $table->unsignedBigInteger('quantity'); // unité de base : pièces, g ou ml
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor'); // TTC
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->string('substitution', 10)->default('allow'); // consigne client : allow | refuse
            // Résultat de la préparation (pesée, rupture, substitution) ; null tant que non préparée.
            $table->unsignedBigInteger('final_product_id')->nullable();
            $table->string('final_name')->nullable();
            $table->unsignedBigInteger('final_quantity')->nullable();
            $table->unsignedBigInteger('final_unit_price_minor')->nullable();
            $table->unsignedBigInteger('final_line_total_minor')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
