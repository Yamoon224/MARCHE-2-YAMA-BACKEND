<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 16)->nullable()->after('slug');
            $table->string('image_url', 500)->nullable()->after('icon');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('description');
            // Ancien prix barré : renseigné = produit en promotion.
            $table->unsignedBigInteger('compare_at_price_minor')->nullable()->after('price_minor');
            $table->boolean('is_featured')->default(false)->index()->after('perishable');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('text', 500)->nullable();
            $table->string('cta_label', 80);
            $table->string('cta_url', 255);
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Quantité dans l'unité de base du produit : pièces, grammes ou millilitres.
            $table->unsignedInteger('quantity');
            $table->unique(['recipe_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('banners');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['image_url', 'compare_at_price_minor', 'is_featured']));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['icon', 'image_url']));
    }
};
