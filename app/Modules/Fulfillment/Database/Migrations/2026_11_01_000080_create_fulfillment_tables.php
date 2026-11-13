<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_id / order_line_id / product_id / picker_id : identifiants d'autres modules, sans clé étrangère.
        Schema::create('pick_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('status', 20)->index(); // open | in_progress | completed
            $table->unsignedBigInteger('picker_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pick_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_list_id')->constrained('pick_lists')->cascadeOnDelete();
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->string('sale_unit', 10);
            $table->unsignedBigInteger('ordered_quantity');   // pièces, g ou ml
            $table->unsignedBigInteger('unit_price_minor');
            $table->string('substitution', 10)->default('allow');
            $table->string('status', 20)->default('pending'); // pending | picked | short | substituted
            $table->unsignedBigInteger('picked_quantity')->nullable();
            $table->unsignedBigInteger('final_product_id')->nullable();
            $table->string('final_name')->nullable();
            $table->unsignedBigInteger('final_unit_price_minor')->nullable();
            $table->timestamps();

            $table->unique(['pick_list_id', 'order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_lines');
        Schema::dropIfExists('pick_lists');
    }
};
