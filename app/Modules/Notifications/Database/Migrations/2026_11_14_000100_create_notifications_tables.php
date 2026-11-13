<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id : identifiant du module Identity, sans clé étrangère (frontière de module).
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 40);
            $table->string('title');
            $table->text('body');
            $table->string('dedup_key', 100)->unique(); // un même événement ne notifie qu'une fois
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_notification_id')->constrained('user_notifications')->cascadeOnDelete();
            $table->string('medium', 10); // sms | email
            $table->text('recipient');     // chiffré : numéro ou adresse
            $table->string('status', 10)->index(); // pending | sent | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_notification_id', 'medium']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('user_notifications');
    }
};
