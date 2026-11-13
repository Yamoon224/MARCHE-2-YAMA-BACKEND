<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_id : identifiant du module Orders, sans clé étrangère (frontière de module).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('provider', 30);
            $table->string('provider_reference', 80)->nullable()->unique();
            $table->string('method', 30);
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('idempotency_key', 80)->nullable();
            $table->boolean('needs_refund')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'idempotency_key']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('payment_id')->constrained('payments');
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 20)->index(); // pending | succeeded | failed
            $table->string('kind', 20);            // adjustment | cancellation | manual | late_payment
            $table->string('reason');
            $table->string('source_key', 80)->unique(); // idempotence : un même fait déclencheur ne rembourse qu'une fois
            $table->string('provider_reference', 80)->nullable();
            $table->string('failure')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 100);
            $table->string('outcome', 30);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
