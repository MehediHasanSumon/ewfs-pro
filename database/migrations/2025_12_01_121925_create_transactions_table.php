<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 150)->nullable();
            $table->string('ac_number', 150);
            $table->enum('transaction_type', ['Dr', 'Cr']);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->enum('payment_type', ['cash', 'bank', 'mobile bank'])->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('cheque_type')->nullable();
            $table->string('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('mobile_bank_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->date('transaction_date');
            $table->time('transaction_time');
            $table->timestamps();

            $table->foreign('ac_number')->references('ac_number')->on('accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
