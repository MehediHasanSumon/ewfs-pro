<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_payments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->foreignId('to_account_id')->constrained('accounts');
            $table->enum('type', ['cash', 'bank'])->default('cash');
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_payments');
    }
};
