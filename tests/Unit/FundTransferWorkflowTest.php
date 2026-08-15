<?php

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\FundTransfer;
use App\Models\Group;
use App\Models\Shift;
use App\Services\AccountingService;
use App\Services\DocumentNumberService;
use App\Services\FundTransferService;
use App\Services\LedgerQueryService;
use App\Services\PaymentAccountService;
use App\Services\SystemAccountService;
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
        $table->string('status', 20)->default('draft');
        $table->string('external_reference', 150)->nullable();
        $table->text('description')->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('fund_transfers', function (Blueprint $table): void {
        $table->id();
        $table->string('transfer_no', 100)->unique();
        $table->date('transfer_date');
        $table->foreignId('from_account_id')->constrained('accounts');
        $table->to_account_id = $table->foreignId('to_account_id')->constrained('accounts');
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
});

function seedFundAccounts(): array
{
    $cashGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('cash_in_hand'),
        'name' => 'Cash in hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $bankGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('bank_account'),
        'name' => 'Bank Account',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $mobileBankGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('mobile_bank'),
        'name' => 'Mobile Bank',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $expenseGroup = Group::query()->create([
        'code' => '40001',
        'name' => 'Administrative Expense',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);

    $cashAccount = Account::query()->create([
        'name' => 'Main Office Cash Box',
        'ac_number' => 'CASH-001',
        'semantic_code' => 'cash_on_hand',
        'group_id' => $cashGroup->id,
    ]);

    $dbblAccount = Account::query()->create([
        'name' => 'DBBL Corporate Account',
        'ac_number' => 'BANK-DBBL-001',
        'group_id' => $bankGroup->id,
    ]);

    $cityAccount = Account::query()->create([
        'name' => 'City Bank Account',
        'ac_number' => 'BANK-CITY-001',
        'group_id' => $bankGroup->id,
    ]);

    $bkashAccount = Account::query()->create([
        'name' => 'bKash Merchant Account',
        'ac_number' => 'MBANK-BKASH-001',
        'group_id' => $mobileBankGroup->id,
    ]);

    $bankChargeAccount = Account::query()->create([
        'name' => 'Bank Charges and Fees',
        'ac_number' => 'EXP-BANK-CHARGE',
        'semantic_code' => 'bank_charge_expense',
        'group_id' => $expenseGroup->id,
    ]);

    return compact(
        'cashAccount',
        'dbblAccount',
        'cityAccount',
        'bkashAccount',
        'bankChargeAccount'
    );
}

it('posts a Cash to Bank internal fund transfer atomically with balanced journal', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 50000.00,
        'transfer_fee' => 0,
        'reference_no' => 'DEP-10023',
        'remarks' => 'Cash deposit from office to DBBL',
    ]);

    expect($transfer)->not->toBeNull()
        ->and($transfer->transfer_no)->toStartWith('TRF-')
        ->and($transfer->status)->toBe('posted')
        ->and((float) $transfer->amount)->toBe(50000.00)
        ->and((float) $transfer->transfer_fee)->toBe(0.00);

    $journal = $transfer->journalEntry;
    expect($journal)->not->toBeNull()
        ->and($journal->event_type)->toBe('fund_transfer')
        ->and($journal->status)->toBe('posted');

    $lines = $journal->lines()->get();
    expect($lines)->toHaveCount(2);

    $debitLine = $lines->firstWhere('debit_amount', '>', 0);
    $creditLine = $lines->firstWhere('credit_amount', '>', 0);

    expect($debitLine->account_id)->toBe($accounts['dbblAccount']->id)
        ->and((float) $debitLine->debit_amount)->toBe(50000.00)
        ->and($creditLine->account_id)->toBe($accounts['cashAccount']->id)
        ->and((float) $creditLine->credit_amount)->toBe(50000.00);

    // Verify Bank Book reflects the transaction
    $ledgerQuery = app(LedgerQueryService::class);
    $bankLedger = $ledgerQuery->accountLedger($accounts['dbblAccount'], '2026-08-01', '2026-08-31');

    expect($bankLedger['transactions'])->toHaveCount(1)
        ->and((float) $bankLedger['total_debit'])->toBe(50000.00)
        ->and((float) $bankLedger['total_credit'])->toBe(0.00);
});

it('posts a Bank to Cash internal fund transfer (withdrawal)', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['dbblAccount']->id,
        'to_account_id' => $accounts['cashAccount']->id,
        'amount' => 20000.00,
        'reference_no' => 'CHQ-889900',
        'remarks' => 'Cash withdrawal from DBBL for office petty cash',
    ]);

    expect($transfer->status)->toBe('posted');

    $lines = $transfer->journalEntry->lines()->get();
    $debitLine = $lines->firstWhere('debit_amount', '>', 0);
    $creditLine = $lines->firstWhere('credit_amount', '>', 0);

    expect($debitLine->account_id)->toBe($accounts['cashAccount']->id)
        ->and((float) $debitLine->debit_amount)->toBe(20000.00)
        ->and($creditLine->account_id)->toBe($accounts['dbblAccount']->id)
        ->and((float) $creditLine->credit_amount)->toBe(20000.00);
});

it('posts a Bank to Bank transfer with separate transfer fee accounting', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['dbblAccount']->id,
        'to_account_id' => $accounts['cityAccount']->id,
        'amount' => 100000.00,
        'transfer_fee' => 150.00,
        'fee_account_id' => $accounts['bankChargeAccount']->id,
        'reference_no' => 'NPSB-998811',
        'remarks' => 'Inter-bank fund transfer DBBL to City Bank',
    ]);

    expect($transfer->status)->toBe('posted')
        ->and((float) $transfer->amount)->toBe(100000.00)
        ->and((float) $transfer->transfer_fee)->toBe(150.00)
        ->and($transfer->total_deduction)->toBe(100150.00);

    $lines = $transfer->journalEntry->lines()->get();
    expect($lines)->toHaveCount(3);

    $destLine = $lines->firstWhere('account_id', $accounts['cityAccount']->id);
    $feeLine = $lines->firstWhere('account_id', $accounts['bankChargeAccount']->id);
    $sourceLine = $lines->firstWhere('account_id', $accounts['dbblAccount']->id);

    expect((float) $destLine->debit_amount)->toBe(100000.00)
        ->and((float) $destLine->credit_amount)->toBe(0.00)
        ->and((float) $feeLine->debit_amount)->toBe(150.00)
        ->and((float) $feeLine->credit_amount)->toBe(0.00)
        ->and((float) $sourceLine->debit_amount)->toBe(0.00)
        ->and((float) $sourceLine->credit_amount)->toBe(100150.00);
});

it('rejects transfer between the same account', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    expect(fn () => $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['dbblAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 5000.00,
    ]))->toThrow(ValidationException::class);
});

it('rejects transfer with zero or negative amount', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    expect(fn () => $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 0.00,
    ]))->toThrow(ValidationException::class);
});

it('atomically cancels a fund transfer and reverses its journal entry', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 15000.00,
    ]);

    $service->cancel($transfer, 'Test cancellation');

    $transfer->refresh();
    expect($transfer->status)->toBe('cancelled');

    $transfer->journalEntry->refresh();
    expect($transfer->journalEntry->status)->toBe('reversed');
});

it('atomically replaces a fund transfer on edit workflow', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $original = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 10000.00,
    ]);

    $replaced = $service->replace($original, [
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 12000.00,
        'remarks' => 'Updated amount',
    ]);

    $original->refresh();
    expect($original->status)->toBe('cancelled');
    expect($original->journalEntry->status)->toBe('reversed');

    expect($replaced)->not->toBeNull()
        ->and($replaced->id)->not->toBe($original->id)
        ->and((float) $replaced->amount)->toBe(12000.00)
        ->and($replaced->status)->toBe('posted')
        ->and($replaced->remarks)->toBe('Updated amount');
});

it('preserves exact user-provided remark when submitted', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 30000.00,
        'remarks' => 'Cash deposited to DBBL for daily banking.',
    ]);

    expect($transfer->remarks)->toBe('Cash deposited to DBBL for daily banking.')
        ->and($transfer->journalEntry->description)->toBe('Cash deposited to DBBL for daily banking.');
});

it('automatically generates remark when remark is omitted or only whitespace', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    // Case 1: omitted remarks
    $transfer1 = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 30000.00,
    ]);

    expect($transfer1->remarks)->toBe('Fund transfer from Main Office Cash Box to DBBL Corporate Account.')
        ->and($transfer1->journalEntry->description)->toBe('Fund transfer from Main Office Cash Box to DBBL Corporate Account.');

    // Case 2: whitespace-only remarks
    $transfer2 = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['dbblAccount']->id,
        'to_account_id' => $accounts['cityAccount']->id,
        'amount' => 50000.00,
        'remarks' => '     ',
    ]);

    expect($transfer2->remarks)->toBe('Fund transfer from DBBL Corporate Account to City Bank Account.');
});

it('automatically generates remark including transfer fee when fee exists', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $transfer = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['dbblAccount']->id,
        'to_account_id' => $accounts['cityAccount']->id,
        'amount' => 100000.00,
        'transfer_fee' => 100.00,
        'fee_account_id' => $accounts['bankChargeAccount']->id,
        'remarks' => '',
    ]);

    expect($transfer->remarks)->toBe('Fund transfer from DBBL Corporate Account to City Bank Account with transfer fee of 100.')
        ->and($transfer->journalEntry->description)->toBe('Fund transfer from DBBL Corporate Account to City Bank Account with transfer fee of 100.');
});

it('regenerates remark when editing transfer with cleared remarks', function (): void {
    $accounts = seedFundAccounts();
    $service = app(FundTransferService::class);

    $original = $service->create([
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['dbblAccount']->id,
        'amount' => 10000.00,
        'remarks' => 'Old custom remark',
    ]);

    expect($original->remarks)->toBe('Old custom remark');

    // Edit and clear remarks
    $replaced = $service->replace($original, [
        'transfer_date' => '2026-08-15',
        'from_account_id' => $accounts['cashAccount']->id,
        'to_account_id' => $accounts['cityAccount']->id,
        'amount' => 15000.00,
        'remarks' => '   ',
    ]);

    expect($replaced->remarks)->toBe('Fund transfer from Main Office Cash Box to City Bank Account.');
});

