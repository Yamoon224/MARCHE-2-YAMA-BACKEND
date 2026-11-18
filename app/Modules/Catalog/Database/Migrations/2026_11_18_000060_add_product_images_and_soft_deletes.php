<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Chemin sur le disque « public » (fichier téléversé) ou URL absolue (image externe historique).
            $table->string('path', 500);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        // L'image unique historique devient la première image (et la couverture) du produit.
        $now = now();
        DB::table('products')->whereNotNull('image_url')->where('image_url', '!=', '')->orderBy('id')->each(function ($product) use ($now) {
            DB::table('product_images')->insert([
                'product_id' => $product->id, 'path' => $product->image_url, 'position' => 0, 'is_cover' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
