<?php

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Voucher;
use App\Services\AccountingService;
use App\Services\CustomerSecurityDepositService;
use App\Services\DocumentNumberService;
use App\Services\FinancialReportService;
use App\Services\PartyLedgerService;
use App\Services\SystemAccountService;
use App\Services\VoucherPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('accounts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('ac_number');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
    });

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
    });

    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->string('entry_no');
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('event_type');
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('posted');
        $table->unsignedBigInteger('reversal_of_id')->nullable();
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
        $table->string('balance_type');
        $table->date('effective_date');
        $table->decimal('amount', 24, 4);
        $table->foreignId('journal_entry_id');
        $table->string('status')->default('posted');
        $table->timestamps();
    });

    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('status')->default(true);
    });

    Schema::create('voucher_transaction_types', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->foreignId('voucher_category_id');
        $table->string('voucher_type');
        $table->boolean('status')->default(true);
    });

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('voucher_no');
        $table->string('voucher_type');
        $table->date('voucher_date');
        $table->time('voucher_time')->nullable();
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->foreignId('voucher_category_id')->nullable();
        $table->foreignId('voucher_transaction_type_id')->nullable();
        $table->unsignedBigInteger('journal_entry_id')->nullable();
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
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('supplier_id')->nullable();
        $table->foreignId('employee_id')->nullable();
        $table->text('description')->nullable();
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
});

function makeRefundFixture(): array
{
    $cash = Account::query()->create([
        'name' => 'Office Cash',
        'ac_number' => '1001',
        'status' => true,
    ]);
    $customerAccount = Account::query()->create([
        'name' => 'Rahim Customer Account',
        'ac_number' => '2001',
        'status' => true,
    ]);
    $equity = Account::query()->create([
        'name' => 'Opening Equity',
        'ac_number' => '3001',
        'status' => true,
    ]);
    $customer = Customer::query()->create([
        'account_id' => $customerAccount->id,
        'name' => 'Rahim',
        'status' => true,
    ]);

    $categoryId = DB::table('voucher_categories')->insertGetId([
        'name' => 'Customer',
        'status' => true,
    ]);
    $transactionTypeId = DB::table('voucher_transaction_types')->insertGetId([
        'code' => VoucherTransactionTypeHelper::customerSecurityDepositRefundCode(),
        'name' => 'Refund Given',
        'voucher_category_id' => $categoryId,
        'voucher_type' => 'payment',
        'status' => true,
    ]);

    $depositEntry = DB::table('journal_entries')->insertGetId([
        'entry_no' => 'JRN-DEP-1',
        'business_date' => '2026-07-29',
        'occurred_at' => '2026-07-29 09:00:00',
        'event_type' => 'customer_security_deposit',
        'source_type' => Customer::class,
        'source_id' => $customer->id,
        'reference_no' => 'DEP-00001',
        'description' => 'Opening Security Deposit',
        'status' => 'posted',
        'idempotency_key' => 'deposit-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('journal_lines')->insert([
        [
            'journal_entry_id' => $depositEntry,
            'line_no' => 1,
            'account_id' => $equity->id,
            'debit_amount' => 40000,
            'credit_amount' => 0,
            'customer_id' => null,
            'description' => 'Opening Security Deposit',
            'created_at' => now(),
        ],
        [
            'journal_entry_id' => $depositEntry,
            'line_no' => 2,
            'account_id' => $customerAccount->id,
            'debit_amount' => 0,
            'credit_amount' => 40000,
            'customer_id' => $customer->id,
            'description' => 'Opening Security Deposit',
            'created_at' => now(),
        ],
    ]);
    DB::table('party_opening_balances')->insert([
        'customer_id' => $customer->id,
        'balance_type' => 'customer_deposit',
        'effective_date' => '2026-07-29',
        'amount' => 40000,
        'journal_entry_id' => $depositEntry,
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')
        ->zeroOrMoreTimes()
        ->andReturnUsing(
            fn (string $type, string $prefix) => match ($type) {
                'voucher' => $prefix.'0001',
                default => $prefix.'000001',
            }
        );
    $accounting = Mockery::mock(AccountingService::class);
    $journalNumber = 0;
    $accounting->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $entry, array $lines) use (&$journalNumber) {
            $journalNumber++;
            $journalId = DB::table('journal_entries')->insertGetId([
                'entry_no' => 'JRN-'.$journalNumber,
                'business_date' => $entry['business_date'],
                'occurred_at' => now(),
                'event_type' => $entry['event_type'],
                'source_type' => $entry['source_type'],
                'source_id' => $entry['source_id'] ?? null,
                'reference_no' => $entry['reference_no'] ?? null,
                'description' => $entry['description'] ?? null,
                'status' => 'posted',
                'reversal_of_id' => $entry['reversal_of_id'] ?? null,
                'idempotency_key' => $entry['idempotency_key'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'customer_id' => $line['customer_id'] ?? null,
                    'payment_method' => $line['payment_method'] ?? null,
                    'description' => $line['description'] ?? null,
                    'created_at' => now(),
                ]);
            }

            return JournalEntry::query()->findOrFail($journalId);
        });
    $accounting->shouldReceive('reverse')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (JournalEntry $entry, string $reason) {
            $entry->load('lines');
            $reversalId = DB::table('journal_entries')->insertGetId([
                'entry_no' => 'JRN-REV-'.$entry->id,
                'business_date' => '2026-07-29',
                'occurred_at' => now(),
                'event_type' => $entry->event_type.'_reversal',
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reference_no' => $entry->reference_no,
                'description' => $reason,
                'status' => 'posted',
                'reversal_of_id' => $entry->id,
                'idempotency_key' => 'reverse-'.$entry->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($entry->lines as $line) {
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $reversalId,
                    'line_no' => $line->line_no,
                    'account_id' => $line->account_id,
                    'debit_amount' => $line->credit_amount,
                    'credit_amount' => $line->debit_amount,
                    'customer_id' => $line->customer_id,
                    'payment_method' => $line->payment_method,
                    'description' => $reason,
                    'created_at' => now(),
                ]);
            }
            $entry->update(['status' => 'reversed']);

            return JournalEntry::query()->findOrFail($reversalId);
        });

    $service = new VoucherPostingService(
        $accounting,
        Mockery::mock(SystemAccountService::class),
        $numbers,
        app(CustomerSecurityDepositService::class)
    );

    return compact(
        'service',
        'cash',
        'customer',
        'customerAccount',
        'categoryId',
        'transactionTypeId'
    );
}

function refundPayload(array $fixture, float $amount): array
{
    return [
        'date' => '2026-07-29',
        'shift_id' => null,
        'vouchers' => [[
            'voucher_category_id' => $fixture['categoryId'],
            'voucher_transaction_type_id' => $fixture['transactionTypeId'],
            'from_account_id' => $fixture['cash']->id,
            'to_account_id' => $fixture['customerAccount']->id,
            'amount' => $amount,
            'payment_method' => 'Cash',
            'description' => null,
            'remarks' => 'Returning part of security deposit',
        ]],
    ];
}

it('posts a security deposit refund through the payment voucher workflow', function () {
    $fixture = makeRefundFixture();
    $categoryCount = DB::table('voucher_categories')->count();
    $transactionTypeCount = DB::table('voucher_transaction_types')->count();

    $voucher = $fixture['service']->createMany(
        'payment',
        refundPayload($fixture, 10000)
    )[0];

    $metrics = app(PartyLedgerService::class)
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);
    $payments = app(PartyLedgerService::class)
        ->customerPayments($fixture['customer']->id);
    $statement = app(PartyLedgerService::class)
        ->statement($fixture['customerAccount'], 'customer');
    $positionMethod = new ReflectionMethod(
        FinancialReportService::class,
        'customerPosition'
    );
    $positionMethod->setAccessible(true);
    $position = $positionMethod->invoke(
        app(FinancialReportService::class),
        '2026-07-29'
    );

    expect($voucher->status)->toBe('posted')
        ->and($voucher->voucher_transaction_type_id)
        ->toBe($fixture['transactionTypeId'])
        ->and($voucher->journalEntry?->event_type)
        ->toBe(CustomerSecurityDepositService::REFUND_EVENT_TYPE)
        ->and($voucher->journalEntry?->lines->where('account_id', $fixture['customerAccount']->id)->first()->debit_amount)
        ->toBe('10000.0000')
        ->and($voucher->journalEntry?->lines->where('account_id', $fixture['cash']->id)->first()->credit_amount)
        ->toBe('10000.0000')
        ->and(Voucher::query()
            ->posted()
            ->ofType('payment')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted())
            ->count())
        ->toBe(1)
        ->and($metrics['security_deposit'])->toBe(30000.0)
        ->and($metrics['total_paid'])->toBe(0.0)
        ->and($metrics['total_sales'])->toBe(0.0)
        ->and($metrics['current_due'])->toBe(0.0)
        ->and($statement->pluck('type')->all())
        ->toBe([
            'Customer Security Deposit',
            'Customer Security Deposit Refund',
        ])
        ->and($statement->last()['balance'])->toBe(0.0)
        ->and($payments)->toHaveCount(2)
        ->and($payments->firstWhere('sub_type', 'Security Deposit Refund')['amount'])
        ->toBe(10000.0)
        ->and($position['security'])->toBe(30000.0)
        ->and($position['due'])->toBe(0.0)
        ->and($position['advance'])->toBe(0.0)
        ->and($categoryCount)->toBe(DB::table('voucher_categories')->count())
        ->and($transactionTypeCount)
        ->toBe(DB::table('voucher_transaction_types')->count());
});

it('rejects a refund above the available security deposit and rolls back', function () {
    $fixture = makeRefundFixture();

    try {
        $fixture['service']->createMany(
            'payment',
            refundPayload($fixture, 50000)
        );
        $this->fail('Expected the over-refund validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['vouchers.0.amount'][0])
            ->toBe(
                'Refund amount cannot exceed the customer\'s available Security Deposit.'
            );
    }

    expect(Voucher::query()->count())->toBe(0)
        ->and(DB::table('journal_entries')
            ->where('event_type', CustomerSecurityDepositService::REFUND_EVENT_TYPE)
            ->count())
        ->toBe(0)
        ->and(app(CustomerSecurityDepositService::class)
            ->availableForAccount($fixture['customerAccount']->id))
        ->toBe(40000.0);
});

it('restores the available deposit when a refund voucher is reversed', function () {
    $fixture = makeRefundFixture();
    $voucher = $fixture['service']->createMany(
        'payment',
        refundPayload($fixture, 10000)
    )[0];

    $fixture['service']->reverse($voucher);

    expect(app(CustomerSecurityDepositService::class)
        ->availableForAccount($fixture['customerAccount']->id))
        ->toBe(40000.0)
        ->and(app(PartyLedgerService::class)
            ->customerPayments($fixture['customer']->id))
        ->toHaveCount(1);
});
