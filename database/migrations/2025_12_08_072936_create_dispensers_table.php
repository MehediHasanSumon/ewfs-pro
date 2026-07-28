<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->nullable()->unique();
            $table->string('dispenser_name', 150);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('dispenser_item')->nullable();
            $table->decimal('opening_reading', 24, 6)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['dispenser_name', 'product_id']);
            $table->index(['product_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensers');
    }
};
