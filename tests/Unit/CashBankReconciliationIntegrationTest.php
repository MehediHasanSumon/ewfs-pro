<?php

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\FundTransfer;
use App\Models\Group;
use App\Models\JournalEntry;
use App\Models\Shift;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\AccountingService;
use App\Services\DailyStatementReportService;
use App\Services\FundTransferService;
use App\Services\LedgerQueryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $table->boolean('is_system')->default(false);
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

    Schema::create('shifts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->time('start_time')->default('06:00:00');
        $table->time('end_time')->default('14:00:00');
        $table->boolean('status')->default(true);
        $table->timestamps();
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

    Schema::create('voucher_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 50)->unique();
        $table->string('name', 100);
        $table->string('voucher_type', 20)->nullable();
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->string('voucher_no', 100)->unique();
        $table->string('voucher_type', 20);
        $table->date('voucher_date');
        $table->time('voucher_time')->nullable();
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('voucher_category_id')->nullable();
        $table->foreignId('voucher_transaction_type_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->decimal('amount', 24, 4)->default(0);
        $table->string('status', 20)->default('draft');
        $table->string('external_reference', 150)->nullable();
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
        $table->foreignId('account_id')->constrained('accounts');
        $table->string('entry_side', 10);
        $table->decimal('amount', 24, 4);
        $table->unsignedSmallInteger('line_no')->default(1);
        $table->timestamps();
    });

    Schema::create('voucher_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_line_id')->constrained('voucher_lines')->cascadeOnDelete();
        $table->string('payment_method', 30)->default('cash');
        $table->timestamps();
    });

    Schema::create('fund_transfers', function (Blueprint $table): void {
        $table->id();
        $table->string('transfer_no', 100)->unique();
        $table->date('transfer_date');
        $table->foreignId('from_account_id')->constrained('accounts');
        $table->foreignId('to_account_id')->constrained('accounts');
        $table->decimal('amount', 24, 4);
        $table->decimal('transfer_fee', 24, 4)->default(0.0000);
        $table->foreignId('fee_account_id')->nullable()->constrained('accounts');
        $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries');
        $table->string('reference_no', 150)->nullable();
        $table->text('remarks')->nullable();
        $table->string('status', 20)->default('posted');
        $table->foreignId('created_by')->nullable();
        $table->foreignId('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->foreignId('cancelled_by')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id')->nullable();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales');
        $table->foreignId('product_id')->nullable();
        $table->string('product_name_snapshot')->nullable();
        $table->string('unit_name_snapshot')->nullable();
        $table->decimal('quantity', 24, 4)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table): void {
        $table->id();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_id')->constrained('credit_sales');
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('customer_name_snapshot')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id')->constrained('credit_sale_customers');
        $table->foreignId('product_id')->nullable();
        $table->string('vehicle_number_snapshot')->nullable();
        $table->string('product_name_snapshot')->nullable();
        $table->string('unit_name_snapshot')->nullable();
        $table->decimal('quantity', 24, 4)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });
});

function seedReconciliationAccounts(): array
{
    // Groups
    $cashGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('cash_in_hand'),
        'name' => 'Cash in Hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $bankGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('bank_account'),
        'name' => 'Bank Accounts',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $expenseGroup = Group::query()->create([
        'code' => '4001',
        'name' => 'Operating Expenses',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);

    $revenueGroup = Group::query()->create([
        'code' => '3001',
        'name' => 'Sales Revenue',
        'account_class' => 'revenue',
        'normal_balance' => 'credit',
    ]);

    $equityGroup = Group::query()->create([
        'code' => '2001',
        'name' => 'Owner Equity',
        'account_class' => 'equity',
        'normal_balance' => 'credit',
    ]);

    // Accounts
    $officeCash = Account::query()->create([
        'name' => 'Office Cash',
        'ac_number' => 'CASH-OFFICE-01',
        'semantic_code' => 'cash_on_hand',
        'group_id' => $cashGroup->id,
        'status' => true,
    ]);

    $pettyCash = Account::query()->create([
        'name' => 'Petty Cash',
        'ac_number' => 'CASH-PETTY-01',
        'group_id' => $cashGroup->id,
        'status' => true,
    ]);

    $dbblBank = Account::query()->create([
        'name' => 'DBBL Bank',
        'ac_number' => 'BANK-DBBL-01',
        'group_id' => $bankGroup->id,
        'status' => true,
    ]);

    $cityBank = Account::query()->create([
        'name' => 'City Bank',
        'ac_number' => 'BANK-CITY-01',
        'group_id' => $bankGroup->id,
        'status' => true,
    ]);

    $bankChargeExpense = Account::query()->create([
        'name' => 'Bank Charges and Fees',
        'ac_number' => 'EXP-BANK-CHARGE',
        'semantic_code' => 'bank_charge_expense',
        'group_id' => $expenseGroup->id,
        'status' => true,
    ]);

    $generalExpense = Account::query()->create([
        'name' => 'Office General Expense',
        'ac_number' => 'EXP-OFFICE-01',
        'group_id' => $expenseGroup->id,
        'status' => true,
    ]);

    $salesRevenue = Account::query()->create([
        'name' => 'Sales Revenue',
        'ac_number' => 'REV-SALES-01',
        'semantic_code' => 'sales_revenue',
        'group_id' => $revenueGroup->id,
        'status' => true,
    ]);

    $openingEquity = Account::query()->create([
        'name' => 'Owner Capital',
        'ac_number' => 'EQ-CAPITAL-01',
        'semantic_code' => 'retained_earnings',
        'group_id' => $equityGroup->id,
        'status' => true,
    ]);

    $shift = Shift::query()->create([
        'name' => 'Morning Shift',
        'start_time' => '06:00:00',
        'end_time' => '14:00:00',
        'status' => true,
    ]);

    return compact(
        'officeCash',
        'pettyCash',
        'dbblBank',
        'cityBank',
        'bankChargeExpense',
        'generalExpense',
        'salesRevenue',
        'openingEquity',
        'shift'
    );
}

it('Scenario 1: reconciles Cash Book with Opening Cash, Shift Deposit, Cash to Bank, and Expense Payment', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $ledgerQuery = app(LedgerQueryService::class);
    $transferService = app(FundTransferService::class);

    // 1. Set Opening Cash = 100,000 on 2026-08-01 (Dr Office Cash 100,000, Cr Capital 100,000)
    $accounting->post([
        'business_date' => '2026-08-01',
        'occurred_at' => '2026-08-01 08:00:00',
        'event_type' => 'opening_balance',
        'reference_no' => 'OPEN-01',
        'description' => 'Opening Cash Balance',
    ], [
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 100000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $env['openingEquity']->id, 'debit_amount' => 0, 'credit_amount' => 100000.00],
    ]);

    // 2. Shift Cash Collection / Deposit = 50,000 on 2026-08-05
    $accounting->post([
        'shift_id' => $env['shift']->id,
        'business_date' => '2026-08-05',
        'occurred_at' => '2026-08-05 14:00:00',
        'event_type' => 'shift_closing',
        'reference_no' => 'SHIFT-101',
        'description' => 'Shift cash deposit',
    ], [
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 50000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $env['salesRevenue']->id, 'debit_amount' => 0, 'credit_amount' => 50000.00],
    ]);

    // 3. Fund Transfer: Cash to Bank = 30,000 on 2026-08-06
    $transferService->create([
        'transfer_date' => '2026-08-06',
        'from_account_id' => $env['officeCash']->id,
        'to_account_id' => $env['dbblBank']->id,
        'amount' => 30000.00,
    ]);

    // 4. Expense Payment = 10,000 on 2026-08-07
    $accounting->post([
        'business_date' => '2026-08-07',
        'occurred_at' => '2026-08-07 10:00:00',
        'event_type' => 'payment_voucher',
        'reference_no' => 'PAY-001',
        'description' => 'Office Expense Payment',
    ], [
        ['account_id' => $env['generalExpense']->id, 'debit_amount' => 10000.00, 'credit_amount' => 0],
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 0, 'credit_amount' => 10000.00, 'payment_method' => 'cash'],
    ]);

    // Verify Cash Book for period 2026-08-01 to 2026-08-31
    $cashLedger = $ledgerQuery->accountLedger($env['officeCash'], '2026-08-01', '2026-08-31');

    // Expected Cash = 100,000 + 50,000 - 30,000 - 10,000 = 110,000
    expect($cashLedger['total_debit'])->toBe(150000.00)
        ->and($cashLedger['total_credit'])->toBe(40000.00)
        ->and($cashLedger['closing_balance'])->toBe(110000.00)
        ->and($cashLedger['transactions'])->toHaveCount(4);

    // Verify filter from 2026-08-05: Opening balance before 2026-08-05 should be exactly 100,000
    $midMonthLedger = $ledgerQuery->accountLedger($env['officeCash'], '2026-08-05', '2026-08-31');
    expect($midMonthLedger['opening_balance'])->toBe(100000.00)
        ->and($midMonthLedger['total_debit'])->toBe(50000.00)
        ->and($midMonthLedger['total_credit'])->toBe(40000.00)
        ->and($midMonthLedger['closing_balance'])->toBe(110000.00);
});

it('Scenario 2: reconciles Bank Book with Opening Bank, Shift Bank Deposit, Cash to Bank, and Bank to Cash', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $ledgerQuery = app(LedgerQueryService::class);
    $transferService = app(FundTransferService::class);

    // 1. Opening Bank = 200,000 on 2026-08-01
    $accounting->post([
        'business_date' => '2026-08-01',
        'occurred_at' => '2026-08-01 08:00:00',
        'event_type' => 'opening_balance',
        'reference_no' => 'OPEN-02',
        'description' => 'Opening DBBL Bank Balance',
    ], [
        ['account_id' => $env['dbblBank']->id, 'debit_amount' => 200000.00, 'credit_amount' => 0, 'payment_method' => 'bank'],
        ['account_id' => $env['openingEquity']->id, 'debit_amount' => 0, 'credit_amount' => 200000.00],
    ]);

    // 2. Shift Bank Deposit = 30,000 on 2026-08-05
    $accounting->post([
        'shift_id' => $env['shift']->id,
        'business_date' => '2026-08-05',
        'occurred_at' => '2026-08-05 14:00:00',
        'event_type' => 'shift_closing',
        'reference_no' => 'SHIFT-102',
        'description' => 'Shift bank deposit',
    ], [
        ['account_id' => $env['dbblBank']->id, 'debit_amount' => 30000.00, 'credit_amount' => 0, 'payment_method' => 'bank'],
        ['account_id' => $env['salesRevenue']->id, 'debit_amount' => 0, 'credit_amount' => 30000.00],
    ]);

    // 3. Cash to Bank = 30,000 on 2026-08-06
    $transferService->create([
        'transfer_date' => '2026-08-06',
        'from_account_id' => $env['officeCash']->id,
        'to_account_id' => $env['dbblBank']->id,
        'amount' => 30000.00,
    ]);

    // 4. Bank to Cash (Withdrawal) = 20,000 on 2026-08-07
    $transferService->create([
        'transfer_date' => '2026-08-07',
        'from_account_id' => $env['dbblBank']->id,
        'to_account_id' => $env['officeCash']->id,
        'amount' => 20000.00,
    ]);

    // Verify DBBL Bank Book
    // Expected Bank = 200,000 + 30,000 + 30,000 - 20,000 = 240,000
    $bankLedger = $ledgerQuery->accountLedger($env['dbblBank'], '2026-08-01', '2026-08-31');

    expect($bankLedger['total_debit'])->toBe(260000.00)
        ->and($bankLedger['total_credit'])->toBe(20000.00)
        ->and($bankLedger['closing_balance'])->toBe(240000.00);
});

it('Scenario 3: Bank A to Bank B updates both bank accounts and leaves Cash unchanged', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $ledgerQuery = app(LedgerQueryService::class);
    $transferService = app(FundTransferService::class);

    // Initial balances
    $accounting->post([
        'business_date' => '2026-08-01',
        'occurred_at' => '2026-08-01 08:00:00',
        'event_type' => 'opening_balance',
        'reference_no' => 'OPEN-03',
        'description' => 'Initial Balances',
    ], [
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 50000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $env['dbblBank']->id, 'debit_amount' => 150000.00, 'credit_amount' => 0, 'payment_method' => 'bank'],
        ['account_id' => $env['cityBank']->id, 'debit_amount' => 20000.00, 'credit_amount' => 0, 'payment_method' => 'bank'],
        ['account_id' => $env['openingEquity']->id, 'debit_amount' => 0, 'credit_amount' => 220000.00],
    ]);

    // Transfer DBBL -> City Bank = 100,000
    $transferService->create([
        'transfer_date' => '2026-08-05',
        'from_account_id' => $env['dbblBank']->id,
        'to_account_id' => $env['cityBank']->id,
        'amount' => 100000.00,
    ]);

    // Check DBBL Bank: 150,000 - 100,000 = 50,000
    $dbblLedger = $ledgerQuery->accountLedger($env['dbblBank'], '2026-08-01', '2026-08-31');
    expect($dbblLedger['closing_balance'])->toBe(50000.00);

    // Check City Bank: 20,000 + 100,000 = 120,000
    $cityLedger = $ledgerQuery->accountLedger($env['cityBank'], '2026-08-01', '2026-08-31');
    expect($cityLedger['closing_balance'])->toBe(120000.00);

    // Check Cash: unchanged = 50,000
    $cashLedger = $ledgerQuery->accountLedger($env['officeCash'], '2026-08-01', '2026-08-31');
    expect($cashLedger['closing_balance'])->toBe(50000.00)
        ->and($cashLedger['total_credit'])->toBe(0.00);
});

it('Scenario 4: Shift Close Cash Deposit + Fund Transfer Out ensures no duplicate posting', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $ledgerQuery = app(LedgerQueryService::class);
    $transferService = app(FundTransferService::class);

    // 1. Shift closes with Cash Collection = 50,000
    $accounting->post([
        'shift_id' => $env['shift']->id,
        'business_date' => '2026-08-10',
        'occurred_at' => '2026-08-10 14:00:00',
        'event_type' => 'shift_closing',
        'reference_no' => 'SHIFT-104',
        'description' => 'Shift cash deposit',
    ], [
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 50000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $env['salesRevenue']->id, 'debit_amount' => 0, 'credit_amount' => 50000.00],
    ]);

    // Current Cash: 50,000
    $cashMid = $ledgerQuery->accountLedger($env['officeCash'], '2026-08-01', '2026-08-31');
    expect($cashMid['closing_balance'])->toBe(50000.00);

    // 2. Transfer Office Cash -> DBBL = 30,000
    $transferService->create([
        'transfer_date' => '2026-08-10',
        'from_account_id' => $env['officeCash']->id,
        'to_account_id' => $env['dbblBank']->id,
        'amount' => 30000.00,
    ]);

    // Final Cash = 50,000 - 30,000 = 20,000
    $cashFinal = $ledgerQuery->accountLedger($env['officeCash'], '2026-08-01', '2026-08-31');
    expect($cashFinal['closing_balance'])->toBe(20000.00);

    // Final DBBL = 30,000
    $dbblFinal = $ledgerQuery->accountLedger($env['dbblBank'], '2026-08-01', '2026-08-31');
    expect($dbblFinal['closing_balance'])->toBe(30000.00);
});

it('Scenario 5: Transfer Fee correctly increases Expense and accounts for total source outflow', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $ledgerQuery = app(LedgerQueryService::class);
    $transferService = app(FundTransferService::class);

    // Initial DBBL = 200,000
    $accounting->post([
        'business_date' => '2026-08-01',
        'occurred_at' => '2026-08-01 08:00:00',
        'event_type' => 'opening_balance',
        'reference_no' => 'OPEN-05',
        'description' => 'Opening DBBL Balance',
    ], [
        ['account_id' => $env['dbblBank']->id, 'debit_amount' => 200000.00, 'credit_amount' => 0, 'payment_method' => 'bank'],
        ['account_id' => $env['openingEquity']->id, 'debit_amount' => 0, 'credit_amount' => 200000.00],
    ]);

    // Transfer DBBL -> City Bank = 100,000 with Fee = 100
    $transfer = $transferService->create([
        'transfer_date' => '2026-08-05',
        'from_account_id' => $env['dbblBank']->id,
        'to_account_id' => $env['cityBank']->id,
        'amount' => 100000.00,
        'transfer_fee' => 100.00,
        'fee_account_id' => $env['bankChargeExpense']->id,
    ]);

    expect($transfer->total_deduction)->toBe(100100.00)
        ->and((float) $transfer->transfer_fee)->toBe(100.00);

    // DBBL decreases by 100,100 -> Final DBBL = 99,900
    $dbblLedger = $ledgerQuery->accountLedger($env['dbblBank'], '2026-08-01', '2026-08-31');
    expect($dbblLedger['closing_balance'])->toBe(99900.00)
        ->and($dbblLedger['total_credit'])->toBe(100100.00);

    // City Bank increases by exactly 100,000 -> Final City Bank = 100,000
    $cityLedger = $ledgerQuery->accountLedger($env['cityBank'], '2026-08-01', '2026-08-31');
    expect($cityLedger['closing_balance'])->toBe(100000.00)
        ->and($cityLedger['total_debit'])->toBe(100000.00);

    // Bank Charge Expense increases by 100
    $feeLedger = $ledgerQuery->accountLedger($env['bankChargeExpense'], '2026-08-01', '2026-08-31');
    expect($feeLedger['closing_balance'])->toBe(100.00);
});

it('Scenario 6: Daily Statement correctly isolates operational vouchers from internal transfers', function (): void {
    $env = seedReconciliationAccounts();
    $accounting = app(AccountingService::class);
    $transferService = app(FundTransferService::class);
    $dailyStatementService = app(DailyStatementReportService::class);

    // Create an operational Received Voucher
    $voucherCategory = VoucherCategory::query()->create([
        'code' => 'CUST-REC-01',
        'name' => 'Customer Receipt',
        'status' => true,
    ]);

    $receiptVoucher = Voucher::query()->create([
        'voucher_no' => 'RV-1001',
        'voucher_date' => '2026-08-15',
        'voucher_type' => 'receipt',
        'voucher_category_id' => $voucherCategory->id,
        'status' => 'draft',
        'amount' => 25000.00,
    ]);

    $receiptVoucher->lines()->create([
        'account_id' => $env['officeCash']->id,
        'entry_side' => 'debit',
        'amount' => 25000.00,
        'line_no' => 1,
    ]);

    $receiptVoucher->lines()->create([
        'account_id' => $env['salesRevenue']->id,
        'entry_side' => 'credit',
        'amount' => 25000.00,
        'line_no' => 2,
    ]);

    $receiptJournal = $accounting->post([
        'business_date' => '2026-08-15',
        'occurred_at' => '2026-08-15 10:00:00',
        'event_type' => 'voucher_receipt',
        'source_type' => Voucher::class,
        'source_id' => $receiptVoucher->id,
        'reference_no' => 'RV-1001',
        'description' => 'Customer collection receipt',
    ], [
        ['account_id' => $env['officeCash']->id, 'debit_amount' => 25000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $env['salesRevenue']->id, 'debit_amount' => 0, 'credit_amount' => 25000.00],
    ]);

    $receiptVoucher->update([
        'journal_entry_id' => $receiptJournal->id,
        'status' => 'posted',
    ]);

    // Create an Internal Fund Transfer
    $transferService->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $env['officeCash']->id,
        'to_account_id' => $env['dbblBank']->id,
        'amount' => 50000.00,
    ]);

    // Query Daily Statement
    $statement = $dailyStatementService->report('2026-08-15', '2026-08-15');

    // Operational cash receipts must contain the 25,000 voucher, but NOT the 50,000 fund transfer
    expect($statement['cashReceived'])->toHaveCount(1)
        ->and((float) $statement['cashReceived']->first()->amount)->toBe(25000.00)
        ->and($statement['cashPayment'])->toHaveCount(0);
});
