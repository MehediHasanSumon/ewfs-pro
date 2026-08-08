<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_extras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')
                ->constrained('payroll_items')
                ->cascadeOnDelete();
            $table->foreignId('voucher_transaction_type_id')
                ->constrained('voucher_transaction_types')
                ->restrictOnDelete();
            $table->decimal('amount', 24, 4);
            $table->text('remarks')->nullable();
            $table->foreignId('payment_voucher_id')
                ->nullable()
                ->unique('payroll_extras_payment_voucher_unique')
                ->constrained('vouchers')
                ->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->index([
                'payroll_item_id',
                'voucher_transaction_type_id',
                'status',
            ], 'payroll_extras_item_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_extras');
    }
};
