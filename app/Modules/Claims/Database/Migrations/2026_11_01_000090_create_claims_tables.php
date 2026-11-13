<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_id / user_id / order_line_id / product_id : identifiants d'autres modules, sans clé étrangère.
        // unique(order_id) : une seule réclamation par commande (limite les abus de réclamations).
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->index(); // open | accepted | rejected
            $table->text('description')->nullable();
            $table->string('resolution', 20)->nullable();   // refund | redelivery
            $table->string('cost_bearer', 20)->nullable();  // supplier | preparation | delivery | store
            $table->unsignedBigInteger('refund_amount_minor')->default(0);
            $table->boolean('refund_completed')->default(false);
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('product_id')->index();
            $table->string('name');
            $table->string('issue', 20); // missing | damaged | expired | non_conforming
            $table->unsignedBigInteger('quantity'); // unité de base : pièces, g ou ml
            $table->unsignedBigInteger('claimable_minor'); // plafond remboursable pour cette ligne
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['claim_id', 'order_line_id']);
        });

        Schema::create('claim_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_photos');
        Schema::dropIfExists('claim_items');
        Schema::dropIfExists('claims');
    }
};
