<?php

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
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

    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->boolean('status')->default(true);
    });

    Schema::create('voucher_transaction_types', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_category_id');
        $table->string('code');
        $table->string('name');
        $table->string('voucher_type');
        $table->boolean('status')->default(true);
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
        $table->foreignId('supplier_id')->nullable();
        $table->foreignId('employee_id')->nullable();
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

function settlementFixture(float $creditSale = 0): array
{
    $customerAccount = Account::query()->create([
        'name' => 'Customer Account',
        'ac_number' => 'AR-001',
        'status' => true,
    ]);
    $cashAccount = Account::query()->create([
        'name' => 'Cash Account',
        'ac_number' => 'CASH-001',
        'status' => true,
    ]);
    $salesAccount = Account::query()->create([
        'name' => 'Sales Account',
        'ac_number' => 'SALE-001',
        'status' => true,
    ]);
    $customer = Customer::query()->create([
        'account_id' => $customerAccount->id,
        'name' => 'Settlement Customer',
        'status' => true,
    ]);
    $categoryId = DB::table('voucher_categories')->insertGetId([
        'code' => 'VC001',
        'name' => 'Customer',
        'status' => true,
    ]);

    $typeIds = [];

    foreach ([
        'advance_return' => 'payment',
        'security_deposit_refund' => 'payment',
        'security_deposit' => 'receipt',
        'due_paid' => 'receipt',
        'advance_payment' => 'receipt',
    ] as $key => $voucherType) {
        $code = $key === 'advance_payment'
            ? VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode()
            : VoucherTransactionTypeHelper::getCode('customer', $key);
        $typeIds[$key] = DB::table('voucher_transaction_types')->insertGetId([
            'voucher_category_id' => $categoryId,
            'code' => $code,
            'name' => str($key)->replace('_', ' ')->title()->toString(),
            'voucher_type' => $voucherType,
            'status' => true,
        ]);
    }

    if ($creditSale > 0) {
        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_no' => 'JRN-SALE-1',
            'business_date' => '2026-08-06',
            'occurred_at' => '2026-08-06 09:00:00',
            'event_type' => 'credit_sale',
            'source_type' => Customer::class,
            'source_id' => $customer->id,
            'reference_no' => 'INV-001',
            'description' => 'Credit sale',
            'status' => 'posted',
            'idempotency_key' => 'credit-sale-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'line_no' => 1,
                'account_id' => $customerAccount->id,
                'debit_amount' => $creditSale,
                'credit_amount' => 0,
                'customer_id' => $customer->id,
                'description' => 'Credit sale',
                'created_at' => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'line_no' => 2,
                'account_id' => $salesAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $creditSale,
                'customer_id' => null,
                'description' => 'Credit sale',
                'created_at' => now(),
            ],
        ]);
    }

    $numbers = Mockery::mock(DocumentNumberService::class);
    $voucherSequence = 0;
    $numbers->shouldReceive('next')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $type, string $prefix) use (&$voucherSequence) {
            $voucherSequence++;

            return $prefix.str_pad((string) $voucherSequence, 4, '0', STR_PAD_LEFT);
        });

    $accounting = Mockery::mock(AccountingService::class);
    $journalSequence = 0;
    $accounting->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $entry, array $lines) use (&$journalSequence) {
            $journalSequence++;
            $journalId = DB::table('journal_entries')->insertGetId([
                'entry_no' => 'JRN-'.$journalSequence,
                'business_date' => $entry['business_date'],
                'occurred_at' => now(),
                'shift_id' => $entry['shift_id'] ?? null,
                'event_type' => $entry['event_type'],
                'source_type' => $entry['source_type'],
                'source_id' => $entry['source_id'] ?? null,
                'reference_no' => $entry['reference_no'] ?? null,
                'description' => $entry['description'] ?? null,
                'status' => 'posted',
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
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                    'payment_method' => $line['payment_method'] ?? null,
                    'description' => $line['description'] ?? null,
                    'created_at' => now(),
                ]);
            }

            return JournalEntry::query()->findOrFail($journalId);
        });

    $securityDeposits = app(CustomerSecurityDepositService::class);
    $ledger = new PartyLedgerService($securityDeposits);
    $voucherPosting = new VoucherPostingService(
        $accounting,
        Mockery::mock(SystemAccountService::class),
        $numbers,
        $securityDeposits,
        $ledger
    );

    return [
        'vouchers' => $voucherPosting,
        'ledger' => $ledger,
        'customer' => $customer,
        'customerAccount' => $customerAccount,
        'cashAccount' => $cashAccount,
        'categoryId' => $categoryId,
        'typeIds' => $typeIds,
    ];
}

function receiptPayload(array $fixture, float $amount, string $type = 'due_paid'): array
{
    return [
        'date' => '2026-08-06',
        'shift_id' => null,
        'vouchers' => [[
            'voucher_category_id' => $fixture['categoryId'],
            'voucher_transaction_type_id' => $fixture['typeIds'][$type],
            'from_account_id' => $fixture['customerAccount']->id,
            'to_account_id' => $fixture['cashAccount']->id,
            'amount' => $amount,
            'payment_method' => 'Cash',
            'remarks' => 'Customer settlement',
        ]],
    ];
}

it('allocates an exact customer payment entirely to due paid', function () {
    $fixture = settlementFixture(1000);

    $created = $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 1000)
    );
    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);

    expect($created)->toHaveCount(1)
        ->and($created[0]->voucherTransactionType->code)
        ->toBe(VoucherTransactionTypeHelper::customerDuePaidCode())
        ->and($created[0]->amount)->toBe(1000.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(0.0);
});

it('posts an overpayment as one receipt and derives advance from the net balance', function () {
    $fixture = settlementFixture(1000);

    $created = $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 1500)
    );
    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);
    $payments = $fixture['ledger']->customerPayments($fixture['customer']->id);
    $statement = $fixture['ledger']->statement(
        $fixture['customerAccount'],
        'customer'
    );

    expect($created)->toHaveCount(1)
        ->and($created[0]->voucherTransactionType->code)
        ->toBe(VoucherTransactionTypeHelper::customerDuePaidCode())
        ->and($created[0]->amount)->toBe(1500.0)
        ->and(DB::table('journal_entries')
            ->where('event_type', 'receipt_voucher')
            ->count())->toBe(1)
        ->and($payments->pluck('transaction_type_code')->all())
        ->toBe([VoucherTransactionTypeHelper::customerDuePaidCode()])
        ->and($payments->pluck('voucher_no')->unique())->toHaveCount(1)
        ->and($statement->pluck('type')->all())
        ->toBe(['Credit Sale', 'Due Paid'])
        ->and($metric['total_paid'])->toBe(1500.0)
        ->and($metric['current_balance'])->toBe(-500.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(500.0);
});

it('derives advance from a regular receipt when the customer has no due', function () {
    $fixture = settlementFixture();

    $created = $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 1000)
    );
    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);

    expect($created)->toHaveCount(1)
        ->and($created[0]->voucherTransactionType->code)
        ->toBe(VoucherTransactionTypeHelper::customerDuePaidCode())
        ->and($metric['current_balance'])->toBe(-1000.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(1000.0);
});

it('reduces only customer advance and rejects an excessive advance return', function () {
    $fixture = settlementFixture();
    $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 1000)
    );

    $payload = [
        'date' => '2026-08-06',
        'shift_id' => null,
        'vouchers' => [[
            'voucher_category_id' => $fixture['categoryId'],
            'voucher_transaction_type_id' => $fixture['typeIds']['advance_return'],
            'from_account_id' => $fixture['cashAccount']->id,
            'to_account_id' => $fixture['customerAccount']->id,
            'amount' => 300,
            'payment_method' => 'Cash',
        ]],
    ];
    $fixture['vouchers']->createMany('payment', $payload);
    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);

    expect($metric['total_paid'])->toBe(700.0)
        ->and($metric['current_balance'])->toBe(-700.0)
        ->and($metric['current_advance'])->toBe(700.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['security_deposit'])->toBe(0.0);

    $payload['vouchers'][0]['amount'] = 701;

    expect(fn () => $fixture['vouchers']->createMany('payment', $payload))
        ->toThrow(ValidationException::class);
});

it('keeps security deposit receipts and refunds separate from due and advance', function () {
    $fixture = settlementFixture();
    $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 5000, 'security_deposit')
    );

    $fixture['vouchers']->createMany('payment', [
        'date' => '2026-08-06',
        'shift_id' => null,
        'vouchers' => [[
            'voucher_category_id' => $fixture['categoryId'],
            'voucher_transaction_type_id' => $fixture['typeIds']['security_deposit_refund'],
            'from_account_id' => $fixture['cashAccount']->id,
            'to_account_id' => $fixture['customerAccount']->id,
            'amount' => 1500,
            'payment_method' => 'Cash',
        ]],
    ]);

    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);

    expect($metric['security_deposit'])->toBe(3500.0)
        ->and($metric['total_paid'])->toBe(0.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(0.0);
});

it('keeps historical advance payment receipts in net paid without exposing a system option', function () {
    $fixture = settlementFixture(1000);
    $fixture['vouchers']->createMany(
        VoucherTransactionTypeHelper::receiptVoucherType(),
        receiptPayload($fixture, 1500, 'advance_payment')
    );

    $metric = $fixture['ledger']
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);
    $configuredCustomerCodes = collect(
        VoucherTransactionTypeHelper::systemTypes()['customer']
    )->pluck('code');

    expect($configuredCustomerCodes)
        ->not->toContain(
            VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode()
        )
        ->and($metric['total_paid'])->toBe(1500.0)
        ->and($metric['current_balance'])->toBe(-500.0)
        ->and($metric['current_advance'])->toBe(500.0);
});

it('retires the legacy customer advance payment transaction type', function () {
    $fixture = settlementFixture();
    $migration = require database_path(
        'migrations/2026_08_06_000021_retire_customer_advance_payment_transaction_type.php'
    );

    $migration->up();

    expect(
        DB::table('voucher_transaction_types')
            ->where('id', $fixture['typeIds']['advance_payment'])
            ->value('status')
    )->toBe(0)
        ->and(fn () => $fixture['vouchers']->createMany(
            VoucherTransactionTypeHelper::receiptVoucherType(),
            receiptPayload($fixture, 100, 'advance_payment')
        ))
        ->toThrow(ValidationException::class);
});
