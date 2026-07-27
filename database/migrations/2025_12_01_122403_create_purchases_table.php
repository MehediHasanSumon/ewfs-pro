<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchase_date');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->string('invoice_no');
            $table->string('memo_no');
            $table->text('remarks')->nullable();
            $table->foreignId('from_account_id')->constrained('accounts');
            $table->decimal('quantity', 18, 2);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 18, 2)->default(0.00);
            $table->decimal('net_total_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0.00);
            $table->decimal('due_amount', 18, 2);
            $table->enum('status', ['paid', 'due', 'partial'])->default('due');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
