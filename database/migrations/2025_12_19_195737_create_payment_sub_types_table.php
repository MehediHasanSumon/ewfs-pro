<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_sub_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->foreignId('voucher_category_id')->constrained('voucher_categories')->restrictOnDelete();
            $table->enum('type', ['payment', 'receipt', 'both']);
            $table->string('report_bucket_code', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['voucher_category_id', 'name']);
            $table->index(['type', 'status', 'id']);
            $table->index(['report_bucket_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sub_types');
    }
};
