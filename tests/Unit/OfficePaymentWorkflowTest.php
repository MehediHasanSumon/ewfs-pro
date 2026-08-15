<?php

use App\Helpers\VoucherCategoryHelper;
use App\Models\Account;
use App\Models\Group;
use App\Models\Shift;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use App\Services\AccountingService;
use App\Services\CustomerSecurityDepositService;
use App\Services\DocumentNumberService;
use App\Services\PartyLedgerService;
use App\Services\SystemAccountService;
use App\Services\VoucherPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
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

    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 150);
        $table->string('code', 64)->unique();
        $table->string('account_class', 30);
        $table->string('normal_balance', 20)->default('debit');
        $table->foreignId('parent_id')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('group_id')->constrained('groups');
        $table->string('ac_number', 150)->unique();
        $table->string('name', 150);
        $table->string('semantic_code', 100)->nullable()->unique();
        $table->char('currency', 3)->default('BDT');
        $table->boolean('is_control_account')->default(false);
        $table->boolean('allow_manual_posting')->default(true);
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
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

    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id')->nullable();
        $table->foreignId('payment_account_id')->nullable();
        $table->string('employee_code')->nullable();
        $table->string('employee_name')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('shifts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->time('start_time')->default('06:00:00');
        $table->time('end_time')->default('14:00:00');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('voucher_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 64)->unique();
        $table->string('name', 150);
        $table->string('report_bucket_code', 100)->nullable();
        $table->text('description')->nullable();
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
    });

    Schema::create('voucher_transaction_types', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_category_id')->constrained('voucher_categories');
        $table->string('code', 64);
        $table->string('name', 150);
        $table->string('voucher_type', 20);
        $table->string('report_bucket_code', 100)->nullable();
        $table->text('description')->nullable();
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
        $table->unique(['voucher_category_id', 'code']);
    });

    Schema::create('accounting_periods', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->date('starts_on')->nullable();
        $table->date('ends_on')->nullable();
        $table->string('status')->default('open');
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
        $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
        $table->unsignedSmallInteger('line_no')->default(1);
        $table->foreignId('account_id')->constrained('accounts');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('payment_method', 30)->nullable();
        $table->string('description', 500)->nullable();
        $table->text('narration')->nullable();
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->string('voucher_no', 100)->unique();
        $table->string('voucher_type', 20);
        $table->date('voucher_date');
        $table->time('voucher_time')->nullable();
        $table->foreignId('shift_id')->nullable()->constrained('shifts');
        $table->foreignId('voucher_category_id')->nullable()->constrained('voucher_categories');
        $table->foreignId('voucher_transaction_type_id')->nullable()->constrained('voucher_transaction_types');
        $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries');
        $table->string('status', 20)->default('draft');
        $table->string('external_reference', 150)->nullable();
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->foreignId('created_by')->nullable();
        $table->foreignId('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->foreignId('reversal_of_id')->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
        $table->unsignedSmallInteger('line_no')->default(1);
        $table->foreignId('account_id')->constrained('accounts');
        $table->enum('entry_side', ['debit', 'credit']);
        $table->decimal('amount', 24, 4);
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('supplier_id')->nullable();
        $table->foreignId('employee_id')->nullable();
        $table->string('description', 500)->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_line_id')->unique()->constrained('voucher_lines')->cascadeOnDelete();
        $table->string('payment_method', 30);
        $table->string('bank_type', 50)->nullable();
        $table->string('bank_name', 150)->nullable();
        $table->string('branch_name', 150)->nullable();
        $table->string('account_number', 100)->nullable();
        $table->string('cheque_number', 100)->nullable();
        $table->date('cheque_date')->nullable();
        $table->string('mobile_bank_name', 100)->nullable();
        $table->string('mobile_number', 50)->nullable();
        $table->string('transaction_reference', 150)->nullable();
        $table->timestamps();
    });

    Schema::create('customer_security_deposits', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
        $table->decimal('deposit_amount', 24, 4)->default(0);
        $table->decimal('refunded_amount', 24, 4)->default(0);
        $table->decimal('available_amount', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('customer_security_deposit_ledgers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
        $table->foreignId('customer_security_deposit_id');
        $table->foreignId('voucher_id');
        $table->foreignId('journal_entry_id');
        $table->string('entry_type', 20);
        $table->decimal('amount', 24, 4);
        $table->decimal('balance_after', 24, 4);
        $table->timestamps();
    });

    Schema::create('party_ledgers', function (Blueprint $table): void {
        $table->id();
        $table->string('party_type', 50);
        $table->unsignedBigInteger('party_id');
        $table->foreignId('account_id')->constrained('accounts');
        $table->foreignId('journal_entry_id')->constrained('journal_entries');
        $table->foreignId('journal_line_id')->constrained('journal_lines');
        $table->date('entry_date');
        $table->enum('entry_side', ['debit', 'credit']);
        $table->decimal('amount', 24, 4);
        $table->decimal('balance_after', 24, 4)->default(0);
        $table->string('reference_type', 100)->nullable();
        $table->unsignedBigInteger('reference_id')->nullable();
        $table->text('narration')->nullable();
        $table->timestamps();
    });
});

it('posts an office payment atomically and resolves category and transaction type', function (): void {
    $expenseGroup = Group::query()->create([
        'code' => '20001',
        'name' => 'Operating Expense',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);
    $cashGroup = Group::query()->create([
        'code' => '100020002',
        'name' => 'Cash in hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $cashAccount = Account::query()->create([
        'name' => 'Main Cash Box',
        'ac_number' => 'CASH-001',
        'group_id' => $cashGroup->id,
        'current_balance' => 10000,
    ]);

    $officeExpenseAccount = Account::query()->create([
        'name' => 'Office Expenses',
        'ac_number' => 'EXP-OFFICE',
        'semantic_code' => 'office_expense',
        'group_id' => $expenseGroup->id,
    ]);

    $operatingCategory = VoucherCategory::query()->create([
        'code' => VoucherCategoryHelper::operatingCode(),
        'name' => 'Operating',
        'status' => true,
        'is_system' => true,
    ]);

    $transactionType = VoucherTransactionType::query()->create([
        'voucher_category_id' => $operatingCategory->id,
        'code' => '2001',
        'name' => 'Office Payment',
        'voucher_type' => 'office_payment',
        'status' => true,
        'is_system' => true,
    ]);

    $shift = Shift::query()->create([
        'name' => 'Morning Shift',
    ]);

    $service = app(VoucherPostingService::class);

    $voucher = $service->createOfficePayment([
        'date' => '2026-08-15',
        'shift_id' => $shift->id,
        'to_account_id' => $cashAccount->id,
        'amount' => 1500.00,
        'payment_type' => 'Cash',
        'remarks' => 'Office tea and supplies',
    ]);

    expect($voucher)->not->toBeNull()
        ->and($voucher->voucher_type)->toBe('office_payment')
        ->and($voucher->voucher_category_id)->toBe($operatingCategory->id)
        ->and($voucher->voucher_transaction_type_id)->toBe($transactionType->id)
        ->and((float) $voucher->amount)->toBe(1500.00)
        ->and($voucher->status)->toBe('posted');

    $voucherLines = $voucher->lines()->get();
    expect($voucherLines)->toHaveCount(2);

    $creditLine = $voucherLines->firstWhere('entry_side', 'credit');
    $debitLine = $voucherLines->firstWhere('entry_side', 'debit');

    expect($creditLine->account_id)->toBe($cashAccount->id)
        ->and((float) $creditLine->amount)->toBe(1500.00)
        ->and($debitLine->account_id)->toBe($officeExpenseAccount->id)
        ->and((float) $debitLine->amount)->toBe(1500.00);

    $journal = $voucher->journalEntry;
    expect($journal)->not->toBeNull()
        ->and((float) $journal->lines()->sum('debit_amount'))->toBe(1500.00)
        ->and($journal->status)->toBe('posted');
});

it('replaces and reverses an office payment atomically during update', function (): void {
    $expenseGroup = Group::query()->create([
        'code' => '20001',
        'name' => 'Operating Expense',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);
    $cashGroup = Group::query()->create([
        'code' => '100020002',
        'name' => 'Cash in hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $cashAccount = Account::query()->create([
        'name' => 'Main Cash Box',
        'ac_number' => 'CASH-001',
        'group_id' => $cashGroup->id,
        'current_balance' => 10000,
    ]);

    Account::query()->create([
        'name' => 'Office Expenses',
        'ac_number' => 'EXP-OFFICE',
        'group_id' => $expenseGroup->id,
    ]);

    $operatingCategory = VoucherCategory::query()->create([
        'code' => VoucherCategoryHelper::operatingCode(),
        'name' => 'Operating',
        'status' => true,
        'is_system' => true,
    ]);

    VoucherTransactionType::query()->create([
        'voucher_category_id' => $operatingCategory->id,
        'code' => '2001',
        'name' => 'Office Payment',
        'voucher_type' => 'office_payment',
        'status' => true,
        'is_system' => true,
    ]);

    $shift = Shift::query()->create([
        'name' => 'Morning Shift',
    ]);

    $service = app(VoucherPostingService::class);

    $originalVoucher = $service->createOfficePayment([
        'date' => '2026-08-15',
        'shift_id' => $shift->id,
        'to_account_id' => $cashAccount->id,
        'amount' => 500.00,
        'payment_type' => 'Cash',
        'remarks' => 'Initial payment',
    ]);

    $updatedVoucher = $service->replace($originalVoucher, [
        'date' => '2026-08-15',
        'shift_id' => $shift->id,
        'to_account_id' => $cashAccount->id,
        'amount' => 800.00,
        'payment_type' => 'Cash',
        'remarks' => 'Updated payment',
    ]);

    $originalVoucher->loadMissing('journalEntry');
    $originalVoucher->journalEntry->refresh();
    expect($originalVoucher->journalEntry->status)->toBe('reversed');

    expect($updatedVoucher)->not->toBeNull()
        ->and((float) $updatedVoucher->amount)->toBe(800.00)
        ->and($updatedVoucher->status)->toBe('posted');
});
