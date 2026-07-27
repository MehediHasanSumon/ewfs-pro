<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->nullable();
            $table->time('sale_time')->nullable();
            $table->string('invoice_no');
            $table->string('memo_no')->nullable();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->string('customer')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->foreignId('product_id')->constrained('products');
            $table->string('category_code')->nullable();
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
        Schema::dropIfExists('sales');
    }
};
