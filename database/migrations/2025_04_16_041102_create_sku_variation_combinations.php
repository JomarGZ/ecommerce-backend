<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sku_variation_combinations', function (Blueprint $table) {
            $table->foreignId('sku_id')->constrained('product_skus')->nullable()->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('product_variations')->nullable()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['sku_id', 'variation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_variation_combinations');
    }
};
