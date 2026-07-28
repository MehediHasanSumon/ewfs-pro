<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('payment_method', 30);
            $table->string('bank_type', 50)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('branch_name', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('mobile_bank_name', 100)->nullable();
            $table->string('mobile_number', 50)->nullable();
            $table->timestamps();

            $table->index(['payment_method', 'account_id'], 'sale_payment_method_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payment_details');
    }
};
