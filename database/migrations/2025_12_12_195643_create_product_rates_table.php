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
        Schema::create('product_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('purchase_price', 24, 6)->nullable();
            $table->decimal('sales_price', 24, 6)->nullable();
            $table->date('effective_date');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'effective_date']);
            $table->index(['product_id', 'status', 'effective_date', 'id'], 'pr_product_active_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_rates');
    }
};
