<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('payment_voucher_id')
                ->nullable()
                ->unique()
                ->constrained('vouchers')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('salary_month');
            $table->unsignedSmallInteger('salary_year');
            $table->decimal('amount', 24, 4);
            $table->string('status', 20)->default('paid');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'salary_year', 'salary_month'],
                'employee_salary_payment_period_unique'
            );
            $table->index(
                ['salary_year', 'salary_month', 'status', 'employee_id'],
                'employee_salary_payment_period_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_payments');
    }
};
