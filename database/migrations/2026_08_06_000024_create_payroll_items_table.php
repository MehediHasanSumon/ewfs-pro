<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_snapshot_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_salary', 24, 4);
            $table->decimal('net_salary', 24, 4);
            $table->decimal('advance_balance', 24, 4)->default(0);
            $table->decimal('advance_applied', 24, 4)->default(0);
            $table->decimal('loan_balance', 24, 4)->default(0);
            $table->decimal('net_payable', 24, 4)->default(0);
            $table->foreignId('payment_voucher_id')->nullable()->unique()->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('employee_salary_payment_id')->nullable()->unique()->constrained('employee_salary_payments')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id'], 'payroll_item_period_employee_unique');
            $table->index(['status', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
