<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Vrai : le stock du produit est géré (réservation FEFO à la commande, refus en cas de rupture).
            // Faux (défaut) : le produit se commande librement, sans lot ni réservation.
            $table->boolean('track_stock')->default(false)->after('perishable');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('track_stock'));
    }
};
