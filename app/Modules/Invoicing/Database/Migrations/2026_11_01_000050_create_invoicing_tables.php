<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compteur par type de document et par exercice : numérotation séquentielle sans trou.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['type', 'year']);
        });

        // order_id / user_id : identifiants d'autres modules, sans clé étrangère (frontière de module).
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('issued_at');
            $table->string('currency', 3);
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');   // TTC
            $table->unsignedBigInteger('vat_minor');
            $table->unsignedBigInteger('net_minor');     // HT = total - TVA
            $table->timestamps();
        });

        // Avoir : seule façon de corriger une facture validée. source_key assure l'idempotence.
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->unsignedBigInteger('order_id')->index();
            $table->string('source_key', 80)->unique();
            $table->timestamp('issued_at');
            $table->string('currency', 3);
            $table->unsignedBigInteger('total_minor');   // TTC
            $table->unsignedBigInteger('vat_minor');
            $table->unsignedBigInteger('net_minor');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('sku', 40)->nullable();
            $table->string('name');
            $table->string('sale_unit', 10)->nullable();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->unsignedBigInteger('vat_minor')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
