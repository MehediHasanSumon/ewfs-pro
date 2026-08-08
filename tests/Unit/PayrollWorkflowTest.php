<?php

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Jobs\ProcessPayrollBatch;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\PayrollItem;
use App\Services\AccountingService;
use App\Services\CustomerSecurityDepositService;
use App\Services\DocumentNumberService;
use App\Services\PartyLedgerService;
use App\Services\PayrollService;
use App\Services\SystemAccountService;
use App\Services\VoucherPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('erp.accounting.payment_groups', [
        'Cash' => ['100020002'],
    ]);

    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->string('account_class');
        $table->string('normal_balance')->default('debit');
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('group_id');
        $table->string('ac_number')->unique();
        $table->string('name');
        $table->string('semantic_code')->nullable();
        $table->string('currency')->default('BDT');
        $table->boolean('is_control_account')->default(false);
        $table->boolean('allow_manual_posting')->default(true);
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    Schema::create('document_sequences', function (Blueprint $table): void {
        $table->id();
        $table->string('document_type');
        $table->string('prefix')->nullable();
        $table->unsignedSmallInteger('fiscal_year');
        $table->unsignedBigInteger('next_number')->default(1);
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamps();
        $table->unique(['document_type', 'fiscal_year']);
    });

    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id');
        $table->foreignId('payment_account_id')->nullable();
        $table->string('employee_code');
        $table->string('employee_name');
        $table->decimal('salary', 24, 4)->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('employee_salary_structures', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('employee_id')->unique();
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
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id')->nullable();
    });
    Schema::create('suppliers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id')->nullable();
    });
    Schema::create('accounting_periods', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->date('starts_on');
        $table->date('ends_on');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('accounting_period_id')->nullable();
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->string('entry_no')->unique();
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('event_type');
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('draft');
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key')->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->unsignedBigInteger('reversed_by')->nullable();
        $table->timestamp('reversed_at')->nullable();
        $table->timestamps();
    });
    Schema::create('journal_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });
    Schema::create('voucher_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });
    Schema::create('voucher_transaction_types', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_category_id');
        $table->string('code');
        $table->string('name');
        $table->string('voucher_type');
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });
    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->string('voucher_no')->unique();
        $table->string('voucher_type');
        $table->date('voucher_date');
        $table->time('voucher_time')->nullable();
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->unsignedBigInteger('voucher_category_id')->nullable();
        $table->unsignedBigInteger('voucher_transaction_type_id')->nullable();
        $table->unsignedBigInteger('journal_entry_id')->nullable();
        $table->string('status')->default('draft');
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->timestamps();
    });
    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->string('entry_side');
        $table->decimal('amount', 24, 4);
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });
    Schema::create('voucher_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_line_id');
        $table->string('payment_method');
        $table->string('bank_type')->nullable();
        $table->string('bank_name')->nullable();
        $table->string('branch_name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('cheque_number')->nullable();
        $table->date('cheque_date')->nullable();
        $table->string('mobile_bank_name')->nullable();
        $table->string('mobile_number')->nullable();
        $table->string('transaction_reference')->nullable();
        $table->timestamps();
    });
    Schema::create('employee_salary_payments', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('employee_id');
        $table->foreignId('payment_voucher_id')->nullable()->unique();
        $table->foreignId('voucher_transaction_type_id');
        $table->unsignedTinyInteger('salary_month');
        $table->unsignedSmallInteger('salary_year');
        $table->decimal('amount', 24, 4);
        $table->string('status');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });
    Schema::create('payroll_periods', function (Blueprint $table): void {
        $table->id();
        $table->unsignedTinyInteger('month');
        $table->unsignedSmallInteger('year');
        $table->string('status');
        $table->date('payable_date')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
        $table->unique(['year', 'month']);
    });
    Schema::create('payroll_snapshots', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('payroll_period_id');
        $table->foreignId('employee_id');
        $table->unsignedBigInteger('department_id')->nullable();
        $table->unsignedBigInteger('designation_id')->nullable();
        $table->string('employee_name');
        $table->string('employee_code')->nullable();
        $table->string('department_name')->nullable();
        $table->string('designation_name')->nullable();
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
        $table->foreignId('payment_account_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('snapshot_hash');
        $table->timestamps();
        $table->unique(['payroll_period_id', 'employee_id']);
    });
    Schema::create('payroll_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('payroll_period_id');
        $table->foreignId('payroll_snapshot_id')->unique();
        $table->foreignId('employee_id');
        $table->decimal('gross_salary', 24, 4);
        $table->decimal('net_salary', 24, 4);
        $table->decimal('advance_balance', 24, 4)->default(0);
        $table->decimal('advance_applied', 24, 4)->default(0);
        $table->decimal('loan_balance', 24, 4)->default(0);
        $table->decimal('net_payable', 24, 4)->default(0);
        $table->foreignId('advance_adjustment_voucher_id')->nullable()->unique();
        $table->foreignId('payment_voucher_id')->nullable()->unique();
        $table->foreignId('employee_salary_payment_id')->nullable()->unique();
        $table->string('status');
        $table->timestamp('processed_at')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
        $table->unique(['payroll_period_id', 'employee_id']);
    });
});

function payrollFixture(): array
{
    $groups = [
        ['code' => '40002', 'name' => 'Employee Management', 'account_class' => 'liability', 'normal_balance' => 'credit'],
        ['code' => '100020002', 'name' => 'Cash in hand', 'account_class' => 'asset', 'normal_balance' => 'debit'],
        ['code' => '2', 'name' => 'Expenses', 'account_class' => 'expense', 'normal_balance' => 'debit'],
    ];
    foreach ($groups as $group) {
        DB::table('groups')->insert([...$group, 'created_at' => now(), 'updated_at' => now()]);
    }
    $employeeAccount = Account::query()->create(['group_id' => 1, 'ac_number' => 'EMP-1', 'name' => 'Employee', 'status' => true]);
    $cash = Account::query()->create(['group_id' => 2, 'ac_number' => 'CASH-1', 'name' => 'Cash', 'status' => true]);

    $employeeCategory = DB::table('voucher_categories')->insertGetId([
        'code' => VoucherCategoryHelper::employeeCode(),
        'name' => 'Employee',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $types = [];
    foreach ([
        'monthly_salary' => [VoucherTransactionTypeHelper::monthlySalaryCode(), 'Monthly Salary', 'payment'],
        'salary_advance' => [VoucherTransactionTypeHelper::employeeSalaryAdvanceCode(), 'Salary Advance', 'payment'],
        'advance_return' => [VoucherTransactionTypeHelper::employeeAdvanceReturnCode(), 'Advance Return', 'receipt'],
        'personal_loan' => [VoucherTransactionTypeHelper::employeePersonalLoanCode(), 'Personal Loan', 'payment'],
        'loan_recovery' => [VoucherTransactionTypeHelper::employeeLoanRecoveryCode(), 'Loan Recovery', 'receipt'],
    ] as $key => [$code, $name, $voucherType]) {
        $types[$key] = DB::table('voucher_transaction_types')->insertGetId([
            'voucher_category_id' => $employeeCategory,
            'code' => $code,
            'name' => $name,
            'voucher_type' => $voucherType,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    DB::table('accounting_periods')->insert([
        'code' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'account_id' => $employeeAccount->id,
        'payment_account_id' => $cash->id,
        'employee_code' => 'EMP001',
        'employee_name' => 'Payroll Employee',
        'salary' => 30000,
        'status' => true,
    ]);
    $employee->salaryStructure()->create([
        'basic_salary' => 30000,
        'gross_salary' => 30000,
    ]);

    $counter = 0;
    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')->zeroOrMoreTimes()->andReturnUsing(
        function () use (&$counter): string {
            $counter++;

            return 'DOC-'.$counter;
        }
    );
    $accounting = new AccountingService($numbers);
    $ledger = app(PartyLedgerService::class);
    $posting = new VoucherPostingService(
        $accounting,
        new SystemAccountService($numbers),
        $numbers,
        app(CustomerSecurityDepositService::class),
        $ledger
    );

    return compact('employee', 'cash', 'types', 'posting', 'ledger');
}

function payrollAdvanceVoucher(array $fixture, float $amount): void
{
    $fixture['posting']->createMany(VoucherTransactionTypeHelper::paymentVoucherType(), [
        'date' => '2026-08-06',
        'vouchers' => [[
            'voucher_category_id' => DB::table('voucher_categories')->where('code', VoucherCategoryHelper::employeeCode())->value('id'),
            'voucher_transaction_type_id' => $fixture['types']['salary_advance'],
            'from_account_id' => $fixture['cash']->id,
            'to_account_id' => $fixture['employee']->account_id,
            'amount' => $amount,
            'payment_method' => 'Cash',
        ]],
    ]);
}

function payrollEmployeePaymentVoucher(
    array $fixture,
    string $type,
    float $amount
): void {
    $fixture['posting']->createMany(VoucherTransactionTypeHelper::paymentVoucherType(), [
        'date' => '2026-08-06',
        'vouchers' => [[
            'voucher_category_id' => DB::table('voucher_categories')->where('code', VoucherCategoryHelper::employeeCode())->value('id'),
            'voucher_transaction_type_id' => $fixture['types'][$type],
            'from_account_id' => $fixture['cash']->id,
            'to_account_id' => $fixture['employee']->account_id,
            'amount' => $amount,
            'payment_method' => 'Cash',
        ]],
    ]);
}

it('creates a snapshot and posts advance adjustment before net salary payment', function (): void {
    $fixture = payrollFixture();
    payrollAdvanceVoucher($fixture, 8000);
    payrollEmployeePaymentVoucher($fixture, 'personal_loan', 5000);
    $period = app(PayrollService::class)->createPeriod([
        'month' => 7,
        'year' => 2026,
        'payable_date' => '2026-08-01',
    ]);

    app(PayrollService::class)->start($period);
    $fixture['employee']->salaryStructure()->update(['gross_salary' => 40000]);
    $replacementCash = Account::query()->create([
        'group_id' => 2,
        'ac_number' => 'CASH-2',
        'name' => 'Replacement Cash',
        'status' => true,
    ]);
    $fixture['employee']->update([
        'payment_account_id' => $replacementCash->id,
    ]);

    $items = app(PayrollService::class)->process($period->fresh(), [
        'date' => '2026-08-06',
        'employee_ids' => [$fixture['employee']->id],
    ]);
    $item = $items->first();
    $metric = $fixture['ledger']->employeeFinancialMetric($fixture['employee']->fresh());
    $salaryExpense = Account::query()
        ->where('semantic_code', 'payroll_salary_expense')
        ->firstOrFail();
    $salaryExpenseDebit = (float) DB::table('journal_lines')
        ->join(
            'journal_entries',
            'journal_entries.id',
            '=',
            'journal_lines.journal_entry_id'
        )
        ->where('journal_lines.account_id', $salaryExpense->id)
        ->where('journal_entries.status', 'posted')
        ->sum('journal_lines.debit_amount');
    $employeeJournalBalance = (float) DB::table('journal_lines')
        ->join(
            'journal_entries',
            'journal_entries.id',
            '=',
            'journal_lines.journal_entry_id'
        )
        ->where('journal_lines.account_id', $fixture['employee']->account_id)
        ->where('journal_entries.status', 'posted')
        ->selectRaw(
            'COALESCE(SUM(journal_lines.debit_amount - journal_lines.credit_amount), 0) AS balance'
        )
        ->value('balance');

    expect((float) $item->snapshot->net_salary)->toBe(30000.0)
        ->and((float) $item->advance_applied)->toBe(8000.0)
        ->and((float) $item->net_payable)->toBe(22000.0)
        ->and($item->advance_adjustment_voucher_id)->not->toBeNull()
        ->and($item->payment_voucher_id)->not->toBeNull()
        ->and($item->paymentVoucher->from_account_id)->toBe($fixture['cash']->id)
        ->and($item->paymentVoucher->to_account_id)->toBe($salaryExpense->id)
        ->and($salaryExpenseDebit)->toBe(30000.0)
        ->and($employeeJournalBalance)->toBe(5000.0)
        ->and((float) $metric['net_advance'])->toBe(0.0)
        ->and((float) $metric['loan_balance'])->toBe(5000.0)
        ->and((float) $item->loan_balance)->toBe(5000.0)
        ->and((float) $metric['paid_salary'])->toBe(22000.0)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(1)
        ->and(PayrollItem::query()->where('status', PayrollItem::STATUS_PAID)->count())->toBe(1);
});

it('keeps payroll salary snapshots immutable', function (): void {
    $fixture = payrollFixture();
    $period = app(PayrollService::class)->createPeriod([
        'month' => 7,
        'year' => 2026,
    ]);
    $period = app(PayrollService::class)->start($period);
    $snapshot = $period->snapshots->first();

    expect(fn () => $snapshot->update(['net_salary' => 1]))
        ->toThrow(ValidationException::class);
});

it('rejects duplicate payroll periods', function (): void {
    $service = app(PayrollService::class);
    $service->createPeriod(['month' => 7, 'year' => 2026]);

    expect(fn () => $service->createPeriod(['month' => 7, 'year' => 2026]))
        ->toThrow(ValidationException::class);
});

it('prevents employee recoveries from exceeding independent balances', function (): void {
    $fixture = payrollFixture();
    payrollAdvanceVoucher($fixture, 1000);
    payrollEmployeePaymentVoucher($fixture, 'personal_loan', 2000);
    $categoryId = DB::table('voucher_categories')
        ->where('code', VoucherCategoryHelper::employeeCode())
        ->value('id');

    $advanceReturn = fn () => $fixture['posting']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        [
            'date' => '2026-08-06',
            'vouchers' => [[
                'voucher_category_id' => $categoryId,
                'voucher_transaction_type_id' => $fixture['types']['advance_return'],
                'from_account_id' => $fixture['employee']->account_id,
                'to_account_id' => $fixture['cash']->id,
                'amount' => 1001,
                'payment_method' => 'Cash',
            ]],
        ]
    );
    $loanRecovery = fn () => $fixture['posting']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        [
            'date' => '2026-08-06',
            'vouchers' => [[
                'voucher_category_id' => $categoryId,
                'voucher_transaction_type_id' => $fixture['types']['loan_recovery'],
                'from_account_id' => $fixture['employee']->account_id,
                'to_account_id' => $fixture['cash']->id,
                'amount' => 2001,
                'payment_method' => 'Cash',
            ]],
        ]
    );

    expect($advanceReturn)->toThrow(ValidationException::class)
        ->and($loanRecovery)->toThrow(ValidationException::class);
});

it('deduplicates queued payroll batches regardless of employee selection order', function (): void {
    $first = new ProcessPayrollBatch(
        10,
        ['employee_ids' => [3, 1, 2]],
        null
    );
    $second = new ProcessPayrollBatch(
        10,
        ['employee_ids' => [2, 3, 1]],
        null
    );

    expect($first->uniqueId())->toBe('10:1-2-3')
        ->and($second->uniqueId())->toBe($first->uniqueId());
});
