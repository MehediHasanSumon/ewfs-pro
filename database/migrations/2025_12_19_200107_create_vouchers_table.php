<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->string('voucher_no')->unique();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->enum('voucher_type', ['Payment', 'Receipt']);
            $table->foreignId('voucher_category_id')->constrained('voucher_categories');
            $table->foreignId('payment_sub_type_id')->constrained('payment_sub_types');
            $table->foreignId('from_account_id')->constrained('accounts');
            $table->foreignId('to_account_id')->constrained('accounts');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
