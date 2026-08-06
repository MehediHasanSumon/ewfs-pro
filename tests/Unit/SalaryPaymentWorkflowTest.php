<?php

use App\Helpers\SalaryPaymentHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Resources\SalaryPaymentEmployeeResource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\Group;
use App\Models\JournalEntry;
use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use App\Services\AccountingService;
use App\Services\CustomerSecurityDepositService;
use App\Services\DocumentNumberService;
use App\Services\PartyLedgerService;
use App\Services\PaymentAccountService;
use App\Services\SalaryPaymentService;
use App\Services\SystemAccountService;
use App\Services\VoucherPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('erp.accounting.payment_groups', [
        'Cash' => ['100020002'],
        'Mobile Bank' => ['100020003'],
        'Bank' => ['100020004'],
    ]);

    Schema::create('groups', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('code');
        $table->string('name');
        $table->string('account_class');
        $table->string('normal_balance')->default('debit');
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table) {
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

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
        $table->foreignId('payment_account_id')->nullable();
        $table->string('employee_code');
        $table->string('employee_name');
        $table->decimal('salary', 24, 4)->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('employee_salary_structures', function (Blueprint $table) {
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

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
    });

    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
    });

    Schema::create('accounting_periods', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->date('starts_on');
        $table->date('ends_on');
        $table->string('status');
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table) {
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

    Schema::create('journal_lines', function (Blueprint $table) {
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
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->string('description')->nullable();
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
    });

    Schema::create('voucher_transaction_types', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_category_id');
        $table->string('code');
        $table->string('name');
        $table->string('voucher_type');
        $table->string('description')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('voucher_no')->unique();
        $table->string('voucher_type');
        $table->date('voucher_date');
        $table->time('voucher_time')->nullable();
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->foreignId('voucher_category_id')->nullable();
        $table->foreignId('voucher_transaction_type_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('status')->default('draft');
        $table->string('external_reference')->nullable();
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table) {
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

    Schema::create('voucher_payment_details', function (Blueprint $table) {
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

    Schema::create('employee_salary_payments', function (Blueprint $table) {
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
        $table->unique([
            'employee_id',
            'salary_year',
            'salary_month',
            'voucher_transaction_type_id',
        ]);
    });
});

function salaryPaymentService(): SalaryPaymentService
{
    $sequence = 0;
    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $type, string $prefix) use (&$sequence): string {
            $sequence++;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    $accounting = new AccountingService($numbers);
    $voucherPosting = new VoucherPostingService(
        $accounting,
        Mockery::mock(SystemAccountService::class),
        $numbers,
        app(CustomerSecurityDepositService::class),
        app(PartyLedgerService::class)
    );

    return new SalaryPaymentService(
        $voucherPosting,
        app(PaymentAccountService::class)
    );
}

function salaryPaymentMasters(): void
{
    $categoryId = DB::table('voucher_categories')->insertGetId([
        'code' => VoucherCategoryHelper::employeeCode(),
        'name' => 'Employee',
        'status' => true,
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('voucher_transaction_types')->insert([
        'voucher_category_id' => $categoryId,
        'code' => VoucherTransactionTypeHelper::monthlySalaryCode(),
        'name' => 'Monthly Salary',
        'voucher_type' => VoucherTransactionTypeHelper::paymentVoucherType(),
        'status' => true,
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function salaryPaymentAccounts(): array
{
    $cashGroup = Group::query()->create([
        'code' => '100020002',
        'name' => 'Cash in hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
        'status' => true,
    ]);
    $employeeGroup = Group::query()->create([
        'code' => '20001',
        'name' => 'Employee Ledger',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
        'status' => true,
    ]);
    $cash = Account::query()->create([
        'group_id' => $cashGroup->id,
        'ac_number' => 'CASH-001',
        'name' => 'Office Cash',
        'status' => true,
    ]);

    return compact('cash', 'employeeGroup');
}

function salaryPaymentEmployee(
    string $name,
    string $code,
    Account $cash,
    Group $employeeGroup,
    float $grossSalary
): Employee {
    $ledger = Account::query()->create([
        'group_id' => $employeeGroup->id,
        'ac_number' => 'EMP-'.$code,
        'name' => "{$name} Ledger",
        'status' => true,
    ]);
    $employee = Employee::query()->create([
        'account_id' => $ledger->id,
        'payment_account_id' => $cash->id,
        'employee_code' => $code,
        'employee_name' => $name,
        'salary' => $grossSalary,
        'status' => true,
    ]);
    $employee->salaryStructure()->create([
        'basic_salary' => $grossSalary,
        'gross_salary' => $grossSalary,
    ]);

    return $employee;
}

function salaryPaymentPayload(array $employeeIds): array
{
    return [
        'date' => '2026-08-04',
        'salary_month' => 8,
        'salary_year' => 2026,
        'voucher_transaction_type_id' => VoucherTransactionType::query()
            ->where('code', VoucherTransactionTypeHelper::monthlySalaryCode())
            ->value('id'),
        'employee_ids' => $employeeIds,
    ];
}

function salaryExtraPaymentType(
    string $name = 'Festival Bonus',
    string $code = '1090'
): VoucherTransactionType {
    return VoucherTransactionType::query()->create([
        'voucher_category_id' => DB::table('voucher_categories')
            ->where('code', VoucherCategoryHelper::employeeCode())
            ->value('id'),
        'code' => $code,
        'name' => $name,
        'voucher_type' => VoucherTransactionTypeHelper::paymentVoucherType(),
        'status' => true,
        'is_system' => false,
    ]);
}

it('creates standard payment vouchers and journals from employee payroll data', function () {
    salaryPaymentMasters();
    $accounts = salaryPaymentAccounts();
    $employee = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );

    $payments = salaryPaymentService()->pay(
        salaryPaymentPayload([$employee->id])
    );
    $payment = $payments->first();
    $voucher = Voucher::query()
        ->with([
            'voucherCategory',
            'voucherTransactionType',
            'lines.paymentDetail',
            'journalEntry.lines',
        ])
        ->firstOrFail();

    expect($payments)->toHaveCount(1)
        ->and($payment->employee_id)->toBe($employee->id)
        ->and($payment->amount)->toBe('21000.0000')
        ->and($payment->status)->toBe(EmployeeSalaryPayment::STATUS_PAID)
        ->and($payment->voucher_transaction_type_id)
        ->toBe($voucher->voucher_transaction_type_id)
        ->and($voucher->voucher_type)
        ->toBe(VoucherTransactionTypeHelper::paymentVoucherType())
        ->and($voucher->voucherCategory?->code)
        ->toBe(VoucherCategoryHelper::employeeCode())
        ->and($voucher->voucherTransactionType?->code)
        ->toBe(VoucherTransactionTypeHelper::monthlySalaryCode())
        ->and($voucher->from_account_id)->toBe($accounts['cash']->id)
        ->and($voucher->to_account_id)->toBe($employee->account_id)
        ->and($voucher->shift_id)->toBeNull()
        ->and($voucher->amount)->toBe(21000.0)
        ->and($voucher->payment_method)->toBe('cash')
        ->and($voucher->remarks)
        ->toBe(
            SalaryPaymentHelper::remarks('John Doe', 8, 2026)
        )
        ->and($voucher->journalEntry?->status)->toBe('posted')
        ->and((float) $voucher->journalEntry?->lines
            ->firstWhere('account_id', $employee->account_id)
            ?->debit_amount)
        ->toBe(21000.0)
        ->and((float) $voucher->journalEntry?->lines
            ->firstWhere('account_id', $accounts['cash']->id)
            ?->credit_amount)
        ->toBe(21000.0);

    $employee->load([
        'account',
        'paymentAccount.group',
        'salaryStructure',
        'salaryPayments.paymentVoucher.journalEntry',
    ]);
    $resource = (new SalaryPaymentEmployeeResource(
        $employee,
        true
    ))->resolve();

    expect($resource['payment_method'])->toBe('Cash')
        ->and($resource['monthly_salary'])->toBe(21000.0)
        ->and($resource['payment_status'])->toBe('already_paid')
        ->and($resource['can_select'])->toBeFalse();
});

it('posts multiple employees as one atomic salary payment batch', function () {
    salaryPaymentMasters();
    $accounts = salaryPaymentAccounts();
    $first = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $second = salaryPaymentEmployee(
        'Jane Doe',
        'E002',
        $accounts['cash'],
        $accounts['employeeGroup'],
        26000
    );
    $payload = salaryPaymentPayload([$first->id, $second->id]);
    $payload['remarks'][$second->id] = 'August salary for Jane.';

    $payments = salaryPaymentService()->pay($payload);

    expect($payments)->toHaveCount(2)
        ->and(Voucher::query()->count())->toBe(2)
        ->and(JournalEntry::query()->where('status', 'posted')->count())
        ->toBe(2)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(2)
        ->and(Voucher::query()
            ->where('remarks', 'August salary for Jane.')
            ->exists())
        ->toBeTrue();
});

it('rejects a duplicate employee salary for the same month and year', function () {
    salaryPaymentMasters();
    $accounts = salaryPaymentAccounts();
    $employee = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $service = salaryPaymentService();
    $payload = salaryPaymentPayload([$employee->id]);
    $service->pay($payload);

    try {
        $service->pay($payload);
        $this->fail('Expected duplicate salary payment validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['employee_ids'][0])
            ->toBe(
                'Salary has already been paid for this employee for August 2026.'
            );
    }

    expect(Voucher::query()->count())->toBe(1)
        ->and(JournalEntry::query()->count())->toBe(1)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(1);
});

it('creates separate salary and bonus vouchers for the same employee period', function () {
    salaryPaymentMasters();
    $bonusType = salaryExtraPaymentType();
    $accounts = salaryPaymentAccounts();
    $employee = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $service = salaryPaymentService();

    $service->pay(salaryPaymentPayload([$employee->id]));
    $bonusPayload = salaryPaymentPayload([$employee->id]);
    $bonusPayload['voucher_transaction_type_id'] = $bonusType->id;
    $bonusPayload['amounts'] = [$employee->id => 5000];
    $bonusPayment = $service->pay($bonusPayload)->first();
    $bonusVoucher = Voucher::query()
        ->where('voucher_transaction_type_id', $bonusType->id)
        ->firstOrFail();

    expect(Voucher::query()->count())->toBe(2)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(2)
        ->and($bonusPayment->amount)->toBe('5000.0000')
        ->and($bonusPayment->voucher_transaction_type_id)->toBe($bonusType->id)
        ->and($bonusVoucher->amount)->toBe(5000.0)
        ->and($bonusVoucher->remarks)
        ->toBe('Festival Bonus payment for John Doe for August 2026.')
        ->and($bonusVoucher->journalEntry?->status)->toBe('posted');

    try {
        $service->pay($bonusPayload);
        $this->fail('Expected duplicate bonus payment validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['employee_ids'][0])
            ->toBe(
                'Festival Bonus has already been paid for this employee for August 2026.'
            );
    }

    expect(Voucher::query()->count())->toBe(2)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(2);
});

it('requires employee amounts for non-salary payment types', function () {
    salaryPaymentMasters();
    $bonusType = salaryExtraPaymentType();
    $accounts = salaryPaymentAccounts();
    $employee = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $payload = salaryPaymentPayload([$employee->id]);
    $payload['voucher_transaction_type_id'] = $bonusType->id;

    try {
        salaryPaymentService()->pay($payload);
        $this->fail('Expected an extra payment amount validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors()["amounts.{$employee->id}"][0])
            ->toBe(
                'Enter a valid Festival Bonus amount for John Doe.'
            );
    }

    expect(Voucher::query()->count())->toBe(0)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(0);
});

it('rolls back the complete salary batch when voucher posting fails', function () {
    salaryPaymentMasters();
    $accounts = salaryPaymentAccounts();
    $first = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $second = salaryPaymentEmployee(
        'Jane Doe',
        'E002',
        $accounts['cash'],
        $accounts['employeeGroup'],
        26000
    );
    $posting = Mockery::mock(VoucherPostingService::class);
    $posting->shouldReceive('createMany')
        ->once()
        ->andReturnUsing(function (): array {
            DB::table('vouchers')->insert([
                'voucher_no' => 'TEMP-001',
                'voucher_type' => 'payment',
                'voucher_date' => '2026-08-04',
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new RuntimeException('Simulated posting failure.');
        });
    $service = new SalaryPaymentService(
        $posting,
        app(PaymentAccountService::class)
    );

    expect(fn () => $service->pay(
        salaryPaymentPayload([$first->id, $second->id])
    ))->toThrow(RuntimeException::class, 'Simulated posting failure.');

    expect(Voucher::query()->count())->toBe(0)
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(EmployeeSalaryPayment::query()->count())->toBe(0);
});

it('marks payroll metadata reversed and allows the salary to be paid again', function () {
    salaryPaymentMasters();
    $accounts = salaryPaymentAccounts();
    $employee = salaryPaymentEmployee(
        'John Doe',
        'E001',
        $accounts['cash'],
        $accounts['employeeGroup'],
        21000
    );
    $service = salaryPaymentService();
    $payload = salaryPaymentPayload([$employee->id]);
    $firstPayment = $service->pay($payload)->first();
    $voucher = Voucher::query()->findOrFail(
        $firstPayment->payment_voucher_id
    );

    app(VoucherPostingService::class);
    $postingProperty = new ReflectionProperty(
        SalaryPaymentService::class,
        'vouchers'
    );
    $postingProperty->setAccessible(true);
    $posting = $postingProperty->getValue($service);
    $posting->reverse($voucher);

    expect($firstPayment->refresh()->status)
        ->toBe(EmployeeSalaryPayment::STATUS_REVERSED);

    $secondPayment = $service->pay($payload)->first();

    expect($secondPayment->status)->toBe(EmployeeSalaryPayment::STATUS_PAID)
        ->and($secondPayment->payment_voucher_id)
        ->not->toBe($voucher->id)
        ->and(Voucher::query()->count())->toBe(2);
});
