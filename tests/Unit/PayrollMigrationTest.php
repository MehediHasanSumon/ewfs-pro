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
    ] as $migrationFile) {
        $migration = require database_path('migrations/'.$migrationFile);
        $migration->up();
    }

    expect(Schema::hasColumns('payroll_periods', [
        'month',
        'year',
        'status',
        'payable_date',
        'started_at',
        'completed_at',
        'locked_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('payroll_snapshots', [
            'payroll_period_id',
            'employee_id',
            'department_name',
            'designation_name',
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
            'advance_balance',
            'advance_applied',
            'loan_balance',
            'net_payable',
            'advance_adjustment_voucher_id',
            'payment_voucher_id',
            'employee_salary_payment_id',
            'status',
        ]))->toBeTrue();
});
