<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('product_code', 64)->unique();
            $table->string('product_name', 150);
            $table->string('product_slug', 180)->unique();
            $table->string('country_of_origin', 100)->nullable();
            $table->string('sku', 100)->nullable()->unique();
            $table->boolean('is_inventory_item')->default(true);
            $table->longText('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'status', 'id']);
            $table->index(['unit_id', 'status']);
            $table->index(['product_name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
