<?php

use App\Models\Account;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\CustomerReportService;
use App\Services\DocumentNumberService;
use App\Services\FinancialReportService;
use App\Services\OpeningBalanceService;
use App\Services\PartyLedgerService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('accounts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('ac_number');
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
        $table->string('name');
        $table->string('mobile')->nullable();
        $table->text('address')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->string('entry_no');
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->string('event_type');
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('posted');
        $table->string('idempotency_key')->nullable();
        $table->timestamps();
    });

    Schema::create('journal_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->foreignId('customer_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->text('description')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('party_opening_balances', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('supplier_id')->nullable();
        $table->foreignId('employee_id')->nullable();
        $table->string('balance_type');
        $table->date('effective_date');
        $table->decimal('amount', 24, 4);
        $table->foreignId('journal_entry_id');
        $table->string('status')->default('posted');
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('voucher_no');
        $table->string('voucher_type');
        $table->date('voucher_date');
        $table->unsignedBigInteger('journal_entry_id')->nullable();
        $table->foreignId('voucher_transaction_type_id')->nullable();
        $table->foreignId('voucher_category_id')->nullable();
        $table->string('status')->default('posted');
        $table->string('external_reference')->nullable();
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->string('entry_side');
        $table->decimal('amount', 24, 4);
        $table->foreignId('customer_id')->nullable();
        $table->text('description')->nullable();
    });

    Schema::create('voucher_payment_details', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_line_id');
        $table->string('payment_method');
    });

    Schema::create('voucher_transaction_types', function (Blueprint $table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->foreignId('voucher_category_id')->nullable();
        $table->string('voucher_type')->default('payment');
        $table->boolean('status')->default(true);
    });

    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('shifts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('credit_sales', function (Blueprint $table) {
        $table->id();
        $table->string('memo_no')->nullable();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_id');
        $table->foreignId('customer_id');
        $table->foreignId('journal_entry_id');
    });

    Schema::create('vehicles', function (Blueprint $table) {
        $table->id();
        $table->string('vehicle_number');
    });

    Schema::create('credit_sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->foreignId('vehicle_id')->nullable();
        $table->string('vehicle_number_snapshot')->nullable();
    });
});

function ledgerAccount(string $name, string $number): Account
{
    return Account::query()->create([
        'name' => $name,
        'ac_number' => $number,
    ]);
}

function ledgerJournal(
    int $customerAccountId,
    string $eventType,
    string $reference,
    float $customerDebit,
    float $customerCredit,
    int $offsetAccountId,
    string $date,
    string $description
): JournalEntry {
    $entry = JournalEntry::query()->create([
        'entry_no' => 'JRN-'.str_replace('-', '', $reference),
        'business_date' => $date,
        'occurred_at' => $date.' 09:00:00',
        'event_type' => $eventType,
        'source_type' => Customer::class,
        'reference_no' => $reference,
        'description' => $description,
        'status' => 'posted',
        'idempotency_key' => 'test-'.$reference,
    ]);

    $entry->lines()->createMany([
        [
            'line_no' => 1,
            'account_id' => $customerAccountId,
            'debit_amount' => $customerDebit,
            'credit_amount' => $customerCredit,
            'customer_id' => 1,
            'description' => $description,
        ],
        [
            'line_no' => 2,
            'account_id' => $offsetAccountId,
            'debit_amount' => $customerCredit,
            'credit_amount' => $customerDebit,
            'description' => $description,
        ],
    ]);

    return $entry;
}

it('projects customer metrics and payments from ledger-backed transactions', function () {
    $customerAccount = ledgerAccount('Customer Receivable', 'AR-1');
    $offsetAccount = ledgerAccount('Offset', 'OF-1');
    $cashAccount = ledgerAccount('Cash', 'CASH-1');
    $customer = Customer::query()->create([
        'account_id' => $customerAccount->id,
        'name' => 'Ledger Customer',
        'mobile' => '01700000000',
        'status' => true,
    ]);

    $saleEntry = ledgerJournal(
        $customerAccount->id,
        'credit_sale',
        'INV-00001',
        100,
        0,
        $offsetAccount->id,
        '2026-07-29',
        'Credit sale'
    );
    $creditSaleId = DB::table('credit_sales')->insertGetId([
        'memo_no' => 'M-00045',
    ]);
    DB::table('journal_entries')
        ->where('id', $saleEntry->id)
        ->update([
            'source_type' => CreditSale::class,
            'source_id' => $creditSaleId,
        ]);
    $allocationId = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $creditSaleId,
        'customer_id' => $customer->id,
        'journal_entry_id' => $saleEntry->id,
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $allocationId,
        'vehicle_number_snapshot' => 'ABC-123',
    ]);

    $receiptEntry = ledgerJournal(
        $customerAccount->id,
        'receipt_voucher',
        'RCV-00001',
        0,
        40,
        $cashAccount->id,
        '2026-07-29',
        'Customer receipt'
    );
    $voucherId = DB::table('vouchers')->insertGetId([
        'voucher_no' => 'RCV-00001',
        'voucher_type' => 'receipt',
        'voucher_date' => '2026-07-29',
        'journal_entry_id' => $receiptEntry->id,
        'status' => 'posted',
        'external_reference' => 'Receipt Number',
        'remarks' => 'Received payment',
    ]);
    $paymentLineId = DB::table('voucher_lines')->insertGetId([
        'voucher_id' => $voucherId,
        'line_no' => 1,
        'account_id' => $cashAccount->id,
        'entry_side' => 'debit',
        'amount' => 40,
    ]);
    DB::table('voucher_lines')->insert([
        'voucher_id' => $voucherId,
        'line_no' => 2,
        'account_id' => $customerAccount->id,
        'entry_side' => 'credit',
        'amount' => 40,
        'customer_id' => $customer->id,
    ]);
    DB::table('voucher_payment_details')->insert([
        'voucher_line_id' => $paymentLineId,
        'payment_method' => 'cash',
    ]);

    $depositEntry = ledgerJournal(
        $customerAccount->id,
        'customer_security_deposit',
        'DEP-00001',
        0,
        20,
        $offsetAccount->id,
        '2026-07-28',
        'Opening Security Deposit'
    );
    DB::table('party_opening_balances')->insert([
        'customer_id' => $customer->id,
        'balance_type' => 'customer_deposit',
        'effective_date' => '2026-07-28',
        'amount' => 20,
        'journal_entry_id' => $depositEntry->id,
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ledgerJournal(
        $customerAccount->id,
        'customer_opening_balance',
        'CC001',
        50,
        0,
        $offsetAccount->id,
        '2026-07-27',
        'Customer opening receivable'
    );

    $metrics = app(PartyLedgerService::class)
        ->customerMetrics(collect([$customer]))
        ->get($customer->id);
    $payments = app(PartyLedgerService::class)->customerPayments($customer->id);
    $paymentCount = app(PartyLedgerService::class)
        ->customerPaymentCount($customer->id);
    $statement = app(PartyLedgerService::class)
        ->statement($customerAccount, 'customer');
    $ledger = app(CustomerReportService::class)->ledgerDetails(
        $customer,
        '2026-07-27',
        '2026-07-30'
    );
    $ledgerSummary = app(CustomerReportService::class)
        ->ledgerSummary('2026-07-27', '2026-07-30', $customer->id)
        ->first();
    $outstanding = app(FinancialReportService::class)
        ->outstandingCustomers(5, '2026-07-30')
        ->firstWhere('id', $customer->id);

    expect($metrics['total_sales'])->toBe(100.0)
        ->and($metrics['total_paid'])->toBe(40.0)
        ->and($metrics['security_deposit'])->toBe(20.0)
        ->and($metrics['previous_due'])->toBe(50.0)
        ->and($metrics['current_balance'])->toBe(60.0)
        ->and($metrics['current_due'])->toBe(60.0)
        ->and($metrics['current_advance'])->toBe(0.0)
        ->and($paymentCount)->toBe(1)
        ->and($payments)->toHaveCount(2)
        ->and($payments->firstWhere('sub_type', 'Security Deposit')['amount'])
        ->toBe(20.0)
        ->and($statement->pluck('balance')->all())
        ->toBe([0.0, 0.0, 100.0, 60.0])
        ->and(collect($ledger[0]['transactions'])->pluck('transaction_id')->all())
        ->toBe(['CC001', 'DEP-00001', 'INV-00001', 'RCV-00001'])
        ->and(collect($ledger[0]['transactions'])->pluck('due')->all())
        ->toBe([0.0, 0.0, 100.0, 60.0])
        ->and($ledger[0]['total_debit'])->toBe(40.0)
        ->and($ledger[0]['total_credit'])->toBe(100.0)
        ->and($ledger[0]['total_due'])->toBe(60.0)
        ->and($ledgerSummary->debit)->toBe(40.0)
        ->and($ledgerSummary->credit)->toBe(100.0)
        ->and($ledgerSummary->due)->toBe(60.0)
        ->and($outstanding->balance)->toBe(60.0)
        ->and(collect($ledger[0]['transactions'])
            ->firstWhere('transaction_id', 'INV-00001')->vehicle_no)
        ->toBe('ABC-123')
        ->and(collect($ledger[0]['transactions'])
            ->firstWhere('transaction_id', 'INV-00001')->memo_no)
        ->toBe('M-00045');
});

it('excludes security deposits from paid and advance calculations', function () {
    $customerAccount = ledgerAccount('Customer Receivable', 'AR-1');
    $offsetAccount = ledgerAccount('Offset', 'OF-1');
    $cashAccount = ledgerAccount('Cash', 'CASH-1');
    $customer = Customer::query()->create([
        'account_id' => $customerAccount->id,
        'name' => 'Advance Customer',
        'mobile' => '01700000001',
        'status' => true,
    ]);

    ledgerJournal(
        $customerAccount->id,
        'credit_sale',
        'INV-00002',
        2220,
        0,
        $offsetAccount->id,
        '2026-07-29',
        'Credit sale'
    );
    ledgerJournal(
        $customerAccount->id,
        'receipt_voucher',
        'RCV-00002',
        0,
        4544,
        $cashAccount->id,
        '2026-07-29',
        'Customer receipt'
    );
    $depositEntry = ledgerJournal(
        $customerAccount->id,
        'customer_security_deposit',
        'DEP-00002',
        0,
        40000,
        $offsetAccount->id,
        '2026-07-29',
        'Opening Security Deposit'
    );
    DB::table('party_opening_balances')->insert([
        'customer_id' => $customer->id,
        'balance_type' => 'customer_deposit',
        'effective_date' => '2026-07-29',
        'amount' => 40000,
        'journal_entry_id' => $depositEntry->id,
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $metric = app(PartyLedgerService::class)
        ->customerMetrics(collect([$customer]))
        ->get($customer->id);
    $positionMethod = new ReflectionMethod(
        FinancialReportService::class,
        'customerPosition'
    );
    $positionMethod->setAccessible(true);
    $position = $positionMethod->invoke(
        app(FinancialReportService::class),
        '2026-07-29'
    );

    expect($metric['security_deposit'])->toBe(40000.0)
        ->and($metric['total_sales'])->toBe(2220.0)
        ->and($metric['total_paid'])->toBe(4544.0)
        ->and($metric['current_balance'])->toBe(-2324.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(2324.0)
        ->and($position['due'])->toBe(0.0)
        ->and($position['advance'])->toBe(2324.0)
        ->and($position['security'])->toBe(40000.0);
});

it('posts security deposits with a deposit reference and ledger event', function () {
    $customer = Customer::query()->create([
        'account_id' => 1,
        'name' => 'Deposit Customer',
        'status' => true,
    ]);
    $equity = ledgerAccount('Opening Equity', 'EQ-1');
    $journal = new JournalEntry;
    $journal->id = 901;

    $accounting = Mockery::mock(AccountingService::class);
    $accounting->shouldReceive('post')
        ->once()
        ->withArgs(function (array $entry) {
            return $entry['event_type'] === 'customer_security_deposit'
                && $entry['reference_no'] === 'DEP-00001'
                && $entry['description'] === 'Opening Security Deposit';
        })
        ->andReturn($journal);
    $systemAccounts = Mockery::mock(SystemAccountService::class);
    $systemAccounts->shouldReceive('openingBalanceEquity')->once()->andReturn($equity);
    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')->once()->with(
        'customer_deposit',
        'DEP',
        '2026-07-29',
        5
    )->andReturn('DEP-00001');

    $projection = (new OpeningBalanceService(
        $accounting,
        $systemAccounts,
        $numbers
    ))->customerDeposit($customer, 1000, '2026-07-29');

    expect($projection->status)->toBe('posted')
        ->and((float) $projection->amount)->toBe(1000.0)
        ->and($projection->balance_type)->toBe('customer_deposit');
});
