<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('opening_stock', 18, 2)->default(0.00);
            $table->decimal('current_stock', 18, 2)->default(0.00);
            $table->decimal('reserved_stock', 18, 2)->default(0.00);
            $table->decimal('available_stock', 18, 2)->default(0.00);
            $table->decimal('minimum_stock', 18, 2)->default(0.00);
            $table->decimal('maximum_stock', 18, 2)->nullable();
            $table->timestamps();

            $table->unique('product_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
