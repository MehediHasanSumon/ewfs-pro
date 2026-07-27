<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->nullable();
            $table->time('sale_time')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('memo_no')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('category_code')->nullable();
            $table->enum('type', ['regular', 'previous'])->default('regular');
            $table->decimal('purchase_price', 18, 2)->default(0.00);
            $table->decimal('quantity', 18, 2)->default(0.00);
            $table->decimal('amount', 18, 2)->default(0.00);
            $table->decimal('discount', 18, 2)->default(0.00);
            $table->decimal('total_amount', 18, 2)->default(0.00);
            $table->decimal('paid_amount', 20, 2)->default(0.00);
            $table->decimal('due_amount', 20, 2)->default(0.00);
            $table->string('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_sales');
    }
};
