<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('creates normalized payroll period snapshot and item tables', function (): void {
    foreach ([
        'users',
        'employees',
        'emp_departments',
        'emp_designations',
        'accounts',
        'vouchers',
        'employee_salary_payments',
    ] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
        });
    }

    foreach ([
        '2026_08_06_000022_create_payroll_periods_table.php',
        '2026_08_06_000023_create_payroll_snapshots_table.php',
        '2026_08_06_000024_create_payroll_items_table.php',
        '2026_08_06_000025_add_advance_adjustment_voucher_to_payroll_items.php',
        '2026_08_08_000026_extend_payrolls_for_generation_workflow.php',
        '2026_08_08_000027_create_payroll_deductions_table.php',
        '2026_08_08_000028_create_payroll_extras_table.php',
        '2026_08_08_000029_create_payroll_voucher_links_table.php',
    ] as $migrationFile) {
        $migration = require database_path('migrations/'.$migrationFile);
        $migration->up();
    }

    expect(Schema::hasColumns('payroll_periods', [
        'month',
        'year',
        'status',
        'payroll_code',
        'remarks',
        'payable_date',
        'started_at',
        'generated_at',
        'completed_at',
        'locked_at',
        'cancelled_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_snapshots', [
            'payroll_period_id',
            'employee_id',
            'department_name',
            'designation_name',
            'monthly_salary',
            'gross_salary',
            'net_salary',
            'payment_account_id',
            'payment_method',
            'snapshot_hash',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_items', [
            'payroll_period_id',
            'payroll_snapshot_id',
            'employee_id',
            'monthly_salary',
            'total_deduction',
            'total_bonus',
            'advance_balance',
            'advance_applied',
            'salary_payable',
            'loan_balance',
            'net_payable',
            'advance_adjustment_voucher_id',
            'payment_voucher_id',
            'employee_salary_payment_id',
            'status',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_deductions', [
            'payroll_item_id',
            'amount',
            'reason',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_extras', [
            'payroll_item_id',
            'voucher_transaction_type_id',
            'amount',
            'payment_voucher_id',
            'status',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_voucher_links', [
            'payroll_item_id',
            'payroll_extra_id',
            'voucher_id',
            'role',
            'status',
        ]))->toBeTrue();
});
