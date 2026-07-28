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
        Schema::create('sale_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 100);
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['batch_code', 'sale_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_batches');
    }
};
