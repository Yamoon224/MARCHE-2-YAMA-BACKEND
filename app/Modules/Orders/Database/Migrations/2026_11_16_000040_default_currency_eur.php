<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Livraison à Paris et en Île-de-France : les montants sont en euros (centimes). */
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->string('currency', 3)->default('EUR')->change());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->string('currency', 3)->default('XOF')->change());
    }
};
