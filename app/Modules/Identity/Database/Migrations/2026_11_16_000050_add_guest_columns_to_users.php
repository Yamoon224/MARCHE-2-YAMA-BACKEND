<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commande sans compte : chaque achat « invité » crée un client jetable (sans mot de passe connu) qui porte la commande.
     * Les coordonnées de contact sont séparées de email/phone, uniques, pour ne jamais entrer en collision avec un vrai compte.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->index()->after('role');
            $table->string('contact_email')->nullable()->after('is_guest');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_guest', 'contact_email', 'contact_phone']));
    }
};
