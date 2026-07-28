<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_closing_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_closing_id')->unique()->constrained('shift_closings')->cascadeOnDelete();
            $table->decimal('fuel_sales', 24, 4)->default(0);
            $table->decimal('other_product_sales', 24, 4)->default(0);
            $table->decimal('credit_sales', 24, 4)->default(0);
            $table->decimal('bank_sales', 24, 4)->default(0);
            $table->decimal('cash_sales', 24, 4)->default(0);
            $table->decimal('cash_receipts', 24, 4)->default(0);
            $table->decimal('bank_receipts', 24, 4)->default(0);
            $table->decimal('cash_payments', 24, 4)->default(0);
            $table->decimal('bank_payments', 24, 4)->default(0);
            $table->decimal('office_payments', 24, 4)->default(0);
            $table->decimal('expected_cash', 24, 4)->default(0);
            $table->decimal('actual_cash', 24, 4)->default(0);
            $table->decimal('variance_amount', 24, 4)->default(0);
            $table->unsignedBigInteger('last_journal_line_id')->nullable();
            $table->unsignedBigInteger('last_inventory_movement_id')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->index(['refreshed_at', 'shift_closing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_closing_summaries');
    }
};
