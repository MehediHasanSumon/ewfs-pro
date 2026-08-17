<?php

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\FundTransfer;
use App\Models\Group;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
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
        $table->string('ac_number', 150)->nullable();
        $table->string('name', 150);
        $table->string('semantic_code', 100)->nullable();
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

    Schema::create('voucher_transaction_types', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_category_id')->nullable();
        $table->string('code', 50);
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
        $table->foreignId('account_id')->nullable();
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
        $table->foreignId('fee_account_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
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

    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 150);
        $table->string('mobile', 50)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 50);
        $table->string('code', 20)->nullable();
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('product_name', 150);
        $table->string('product_code', 50)->nullable();
        $table->string('product_type', 50)->default('oil');
        $table->foreignId('unit_id')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->decimal('total_amount', 24, 4)->default(0);
        $table->string('status', 30)->default('completed');
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
        $table->foreignId('product_id')->constrained('products');
        $table->string('product_name_snapshot', 150);
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->decimal('unit_price', 24, 4);
        $table->decimal('quantity', 24, 4);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
        $table->string('payment_method', 30)->default('cash');
        $table->foreignId('account_id')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table): void {
        $table->id();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('customer_id')->nullable();
        $table->decimal('total_amount', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_id')->constrained('credit_sales')->cascadeOnDelete();
        $table->foreignId('customer_id')->constrained('customers');
        $table->string('customer_name_snapshot', 150);
        $table->foreignId('journal_entry_id')->nullable();
        $table->decimal('total_amount', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id')->constrained('credit_sale_customers')->cascadeOnDelete();
        $table->foreignId('product_id')->constrained('products');
        $table->string('product_name_snapshot', 150);
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->string('vehicle_number_snapshot', 100)->nullable();
        $table->decimal('unit_price', 24, 4);
        $table->decimal('quantity', 24, 4);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });
});

it('audits daily statement product sales, cash/bank flows, and General Ledger reconciliation', function (): void {
    $accounting = app(AccountingService::class);
    $transferService = app(FundTransferService::class);
    $dailyStatementService = app(DailyStatementReportService::class);
    $ledgerService = app(LedgerQueryService::class);

    $cashGroup = Group::query()->create([
        'name' => 'Cash in hand',
        'code' => AccountGroupHelper::code('cash_in_hand'),
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $bankGroup = Group::query()->create([
        'name' => 'Bank Account',
        'code' => AccountGroupHelper::code('bank_account'),
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $revenueGroup = Group::query()->create([
        'name' => 'Direct Revenue',
        'code' => '30001',
        'account_class' => 'revenue',
        'normal_balance' => 'credit',
    ]);

    $expenseGroup = Group::query()->create([
        'name' => 'Operating Expense',
        'code' => '20001',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);

    $receivableGroup = Group::query()->create([
        'name' => 'Account Receivable',
        'code' => AccountGroupHelper::code('account_receivable'),
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $officeCash = Account::query()->create([
        'name' => 'Office Cash Account',
        'group_id' => $cashGroup->id,
        'semantic_code' => 'cash_on_hand',
        'status' => true,
    ]);

    $dbblBank = Account::query()->create([
        'name' => 'DBBL Bank Account',
        'group_id' => $bankGroup->id,
        'semantic_code' => 'dbbl_main',
        'status' => true,
    ]);

    $salesRevenue = Account::query()->create([
        'name' => 'Sales Revenue Account',
        'group_id' => $revenueGroup->id,
        'status' => true,
    ]);

    $officeExpense = Account::query()->create([
        'name' => 'General Office Expense',
        'group_id' => $expenseGroup->id,
        'status' => true,
    ]);

    $customerReceivable = Account::query()->create([
        'name' => 'Customer Receivable Account',
        'group_id' => $receivableGroup->id,
        'status' => true,
    ]);

    $customer = Customer::query()->create(['name' => 'Metro Transport Ltd', 'mobile' => '01711000999', 'status' => true]);
    $unit = Unit::query()->create(['name' => 'Litre', 'code' => 'LTR']);
    $octane = Product::query()->create(['product_name' => 'Octane 95', 'product_code' => 'P-01', 'unit_id' => $unit->id, 'status' => true]);
    $diesel = Product::query()->create(['product_name' => 'Diesel Eco', 'product_code' => 'P-02', 'unit_id' => $unit->id, 'status' => true]);
    $shift = Shift::query()->create(['name' => 'Morning Shift', 'status' => true]);

    $date = '2026-08-17';

    // 1. Post POS Cash Sale: Octane 100 L @ 125 = 12,500
    $saleJournal = $accounting->post([
        'shift_id' => $shift->id,
        'business_date' => $date,
        'occurred_at' => "{$date} 08:30:00",
        'event_type' => 'regular_sale',
        'source_type' => Sale::class,
        'source_id' => 101,
        'description' => 'Octane Cash Sale',
    ], [
        ['account_id' => $officeCash->id, 'debit_amount' => 12500.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $salesRevenue->id, 'debit_amount' => 0, 'credit_amount' => 12500.00],
    ]);

    $sale = Sale::query()->create([
        'id' => 101,
        'sale_date' => $date,
        'shift_id' => $shift->id,
        'customer_id' => $customer->id,
        'journal_entry_id' => $saleJournal->id,
        'total_amount' => 12500.00,
        'status' => 'completed',
    ]);

    $sale->items()->create([
        'product_id' => $octane->id,
        'product_name_snapshot' => $octane->product_name,
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 125.00,
        'quantity' => 100,
        'line_total' => 12500.00,
    ]);

    $sale->paymentDetail()->create([
        'payment_method' => 'cash',
    ]);

    // 2. Post Credit Sale: Diesel 200 L @ 105 = 21,000
    $creditSaleJournal = $accounting->post([
        'shift_id' => $shift->id,
        'business_date' => $date,
        'occurred_at' => "{$date} 09:15:00",
        'event_type' => 'credit_sale',
        'source_type' => CreditSale::class,
        'source_id' => 201,
        'description' => 'Diesel Credit Sale',
    ], [
        ['account_id' => $customerReceivable->id, 'debit_amount' => 21000.00, 'credit_amount' => 0],
        ['account_id' => $salesRevenue->id, 'debit_amount' => 0, 'credit_amount' => 21000.00],
    ]);

    $creditSale = CreditSale::query()->create([
        'id' => 201,
        'sale_date' => $date,
        'shift_id' => $shift->id,
        'customer_id' => $customer->id,
        'total_amount' => 21000.00,
    ]);

    $creditCustomer = $creditSale->customers()->create([
        'customer_id' => $customer->id,
        'customer_name_snapshot' => $customer->name,
        'journal_entry_id' => $creditSaleJournal->id,
        'total_amount' => 21000.00,
    ]);

    $creditCustomer->items()->create([
        'product_id' => $diesel->id,
        'product_name_snapshot' => $diesel->product_name,
        'unit_name_snapshot' => 'Litre',
        'vehicle_number_snapshot' => 'DHAKA-METRO-11-2233',
        'unit_price' => 105.00,
        'quantity' => 200,
        'line_total' => 21000.00,
    ]);

    // 3. Post Customer Due Payment in Cash: 5,000
    $custCat = VoucherCategory::query()->create(['code' => 'CUST', 'name' => 'Customer', 'status' => true]);
    $dueType = VoucherTransactionType::query()->create(['voucher_category_id' => $custCat->id, 'code' => '1032', 'name' => 'Due Paid', 'voucher_type' => 'receipt']);

    $receiptJournal = $accounting->post([
        'shift_id' => $shift->id,
        'business_date' => $date,
        'occurred_at' => "{$date} 11:00:00",
        'event_type' => 'voucher_receipt',
        'source_type' => Voucher::class,
        'source_id' => 301,
        'description' => 'Customer Due Collection',
    ], [
        ['account_id' => $officeCash->id, 'debit_amount' => 5000.00, 'credit_amount' => 0, 'payment_method' => 'cash'],
        ['account_id' => $customerReceivable->id, 'debit_amount' => 0, 'credit_amount' => 5000.00],
    ]);

    $receiptVoucher = Voucher::query()->create([
        'id' => 301,
        'voucher_no' => 'RV-001',
        'voucher_type' => 'receipt',
        'voucher_date' => $date,
        'shift_id' => $shift->id,
        'voucher_category_id' => $custCat->id,
        'voucher_transaction_type_id' => $dueType->id,
        'journal_entry_id' => $receiptJournal->id,
        'status' => 'posted',
    ]);

    $rDebit = $receiptVoucher->lines()->create(['account_id' => $officeCash->id, 'entry_side' => 'debit', 'amount' => 5000.00, 'line_no' => 1]);
    $receiptVoucher->lines()->create(['account_id' => $customerReceivable->id, 'entry_side' => 'credit', 'amount' => 5000.00, 'line_no' => 2]);
    $rDebit->paymentDetail()->create(['payment_method' => 'cash', 'account_id' => $officeCash->id]);

    // 4. Post Office Expense in Cash: 3,000
    $expCat = VoucherCategory::query()->create(['code' => 'EXP', 'name' => 'Operating', 'status' => true]);
    $expType = VoucherTransactionType::query()->create(['voucher_category_id' => $expCat->id, 'code' => '2001', 'name' => 'Office Utility', 'voucher_type' => 'payment']);

    $expenseJournal = $accounting->post([
        'shift_id' => $shift->id,
        'business_date' => $date,
        'occurred_at' => "{$date} 14:00:00",
        'event_type' => 'voucher_payment',
        'source_type' => Voucher::class,
        'source_id' => 401,
        'description' => 'Office Utility Expense',
    ], [
        ['account_id' => $officeExpense->id, 'debit_amount' => 3000.00, 'credit_amount' => 0],
        ['account_id' => $officeCash->id, 'debit_amount' => 0, 'credit_amount' => 3000.00, 'payment_method' => 'cash'],
    ]);

    $expVoucher = Voucher::query()->create([
        'id' => 401,
        'voucher_no' => 'PV-001',
        'voucher_type' => 'payment',
        'voucher_date' => $date,
        'shift_id' => $shift->id,
        'voucher_category_id' => $expCat->id,
        'voucher_transaction_type_id' => $expType->id,
        'journal_entry_id' => $expenseJournal->id,
        'status' => 'posted',
    ]);

    $eDebit = $expVoucher->lines()->create(['account_id' => $officeExpense->id, 'entry_side' => 'debit', 'amount' => 3000.00, 'line_no' => 1]);
    $expVoucher->lines()->create(['account_id' => $officeCash->id, 'entry_side' => 'credit', 'amount' => 3000.00, 'line_no' => 2]);
    $eDebit->paymentDetail()->create(['payment_method' => 'cash', 'account_id' => $officeCash->id]);

    // 5. Internal Fund Transfer: Office Cash -> DBBL Bank = 4,000
    $transferService->create([
        'transfer_date' => $date,
        'from_account_id' => $officeCash->id,
        'to_account_id' => $dbblBank->id,
        'amount' => 4000.00,
    ]);

    // Execute Daily Statement Report for the date
    $report = $dailyStatementService->report($date, $date);

    // Verification A: Total Sales
    expect($report['summary']['total_sales'])->toBe(33500.0)
        ->and($report['summary']['cash_sales'])->toBe(12500.0)
        ->and($report['summary']['credit_sales'])->toBe(21000.0)
        ->and($report['summary']['bank_sales'])->toBe(0.0);

    // Verification B: Cash Inflow
    // Cash Sale (12,500) + Due Collection (5,000) = 17,500
    expect($report['cashFlow']['total_receipts'])->toBe(17500.0);

    // Verification C: Cash Outflow
    // Office Expense (3,000) + Transfer Out to Bank (4,000) = 7,000
    expect($report['cashFlow']['total_payments'])->toBe(7000.0);

    // Verification D: Net Movement & Closing Cash in Hand
    expect($report['cashFlow']['closing_balance'])->toBe(10500.0)
        ->and($report['summary']['closing_cash'])->toBe(10500.0);

    // Verification E: Bank Balance
    expect($report['bankFlow']['closing_balance'])->toBe(4000.0);

    // Verification F: Reconcile with General Ledger
    $cashLedger = $ledgerService->accountLedger($officeCash, $date, $date);
    expect($cashLedger['closing_balance'])->toBe($report['cashFlow']['closing_balance']);

    $bankLedger = $ledgerService->accountLedger($dbblBank, $date, $date);
    expect($bankLedger['closing_balance'])->toBe($report['bankFlow']['closing_balance']);

    // Verification G: Customer Detail Credit Sales
    expect($report['customerWiseSales'])->toHaveCount(1)
        ->and($report['customerWiseSales'][0]->customer_name)->toBe('Metro Transport Ltd')
        ->and((float) $report['customerWiseSales'][0]->total_amount)->toBe(21000.0);

    // Verification H: Shift-specific report
    $shiftReport = $dailyStatementService->report($date, $date, $shift->id);
    expect($shiftReport['summary']['total_sales'])->toBe(33500.0)
        ->and($shiftReport['customerWiseSales'])->toHaveCount(1);
});
