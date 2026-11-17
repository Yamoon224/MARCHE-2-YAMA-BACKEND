<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Un rayon « à la une » devient une section de l'accueil (titre et sous-titre éditables).
            $table->boolean('show_on_home')->default(false)->after('is_active');
            $table->string('home_title', 120)->nullable()->after('show_on_home');
            $table->string('home_subtitle', 255)->nullable()->after('home_title');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('eyebrow', 80)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('banners', fn (Blueprint $t) => $t->dropColumn('eyebrow'));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['show_on_home', 'home_title', 'home_subtitle']));
    }
};
