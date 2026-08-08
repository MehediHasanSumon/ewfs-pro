<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('emp_departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('emp_designations')->nullOnDelete();
            $table->string('employee_name', 150);
            $table->string('employee_code', 80)->nullable();
            $table->string('department_name', 150)->nullable();
            $table->string('designation_name', 150)->nullable();
            $table->decimal('basic_salary', 24, 4);
            $table->decimal('home_rent_percent', 8, 4)->default(0);
            $table->decimal('home_rent_amount', 24, 4)->default(0);
            $table->decimal('medical_percent', 8, 4)->default(0);
            $table->decimal('medical_amount', 24, 4)->default(0);
            $table->decimal('conveyance_percent', 8, 4)->default(0);
            $table->decimal('conveyance_amount', 24, 4)->default(0);
            $table->decimal('other_allowances', 24, 4)->default(0);
            $table->decimal('deductions', 24, 4)->default(0);
            $table->decimal('gross_salary', 24, 4);
            $table->decimal('net_salary', 24, 4);
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->string('snapshot_hash', 64);
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id'], 'payroll_snapshot_period_employee_unique');
            $table->index(['employee_id', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_snapshots');
    }
};
