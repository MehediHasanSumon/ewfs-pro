<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_sale_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('white_sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('unit_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('sales_price', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->foreign('white_sale_id')->references('id')->on('white_sales');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('unit_id')->references('id')->on('units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_sale_products');
    }
};