<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 32)->unique();
            $table->enum('inventory_class', ['fuel', 'lubricant', 'merchandise', 'service'])->default('fuel');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['inventory_class', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
