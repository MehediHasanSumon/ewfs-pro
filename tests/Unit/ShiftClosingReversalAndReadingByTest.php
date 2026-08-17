<?php

use App\Helpers\AccountGroupHelper;
use App\Helpers\ErpHelper;
use App\Models\Account;
use App\Models\Category;
use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Employee;
use App\Models\Group;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\DispenserCalculationService;
use App\Services\InventoryService;
use App\Services\LedgerQueryService;
use App\Services\PaymentAccountService;
use App\Services\ShiftClosingService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
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

    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('code', 50)->unique();
        $table->string('category_type', 30)->default('fuel');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 50);
        $table->string('code', 20)->unique();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id')->constrained('categories');
        $table->foreignId('unit_id')->constrained('units');
        $table->string('product_name', 150);
        $table->string('product_code', 50)->unique();
        $table->boolean('is_inventory_item')->default(true);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('product_rates', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        $table->decimal('sales_price', 24, 4);
        $table->decimal('purchase_price', 24, 4)->default(0);
        $table->date('effective_date')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('dispensers', function (Blueprint $table): void {
        $table->id();
        $table->string('dispenser_name', 100);
        $table->foreignId('product_id')->constrained('products');
        $table->string('dispenser_item', 100)->nullable();
        $table->decimal('opening_reading', 24, 6)->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->string('employee_name', 150);
        $table->string('employee_code', 50)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('stocks', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
        $table->decimal('opening_stock', 24, 6)->default(0);
        $table->decimal('current_stock', 24, 6)->default(0);
        $table->decimal('reserved_stock', 24, 6)->default(0);
        $table->decimal('available_stock', 24, 6)->default(0);
        $table->decimal('minimum_stock', 24, 6)->default(0);
        $table->decimal('maximum_stock', 24, 6)->nullable();
        $table->unsignedBigInteger('last_movement_id')->nullable();
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamp('refreshed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('inventory_movements', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id')->constrained('products');
        $table->foreignId('shift_id')->nullable()->constrained('shifts');
        $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
        $table->date('business_date');
        $table->timestamp('occurred_at')->nullable();
        $table->string('movement_type', 50);
        $table->decimal('quantity_in', 24, 6)->default(0);
        $table->decimal('quantity_out', 24, 6)->default(0);
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('total_cost', 24, 4)->default(0);
        $table->string('source_type', 150);
        $table->unsignedBigInteger('source_id');
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->foreignId('reversal_of_id')->nullable()->constrained('inventory_movements');
        $table->string('idempotency_key', 150)->unique();
        $table->foreignId('posted_by')->nullable()->constrained('users');
        $table->decimal('before_stock', 24, 6)->nullable();
        $table->decimal('after_stock', 24, 6)->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('shift_closings', function (Blueprint $table): void {
        $table->id();
        $table->date('business_date');
        $table->foreignId('shift_id')->constrained('shifts');
        $table->string('status', 20)->default('draft');
        $table->decimal('expected_cash', 24, 4)->default(0);
        $table->decimal('actual_cash', 24, 4)->default(0);
        $table->decimal('variance_amount', 24, 4)->default(0);
        $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('closed_by')->nullable()->constrained('users');
        $table->timestamp('closed_at')->nullable();
        $table->foreignId('reversed_by')->nullable()->constrained('users');
        $table->timestamp('reversed_at')->nullable();
        $table->foreignId('reversal_of_id')->nullable()->constrained('shift_closings');
        $table->unsignedBigInteger('lock_version')->default(0);
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('dispenser_readings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_closing_id')->constrained('shift_closings')->cascadeOnDelete();
        $table->foreignId('dispenser_id')->constrained('dispensers');
        $table->foreignId('product_id')->constrained('products');
        $table->foreignId('employee_id')->nullable()->constrained('employees');
        $table->decimal('start_reading', 24, 6);
        $table->decimal('end_reading', 24, 6);
        $table->decimal('meter_test', 24, 6)->default(0);
        $table->decimal('net_quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('gross_amount', 24, 4);
        $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
        $table->timestamps();
    });

    Schema::create('shift_closing_product_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_closing_id')->constrained('shift_closings')->cascadeOnDelete();
        $table->foreignId('product_id')->constrained('products');
        $table->foreignId('unit_id')->nullable()->constrained('units');
        $table->foreignId('employee_id')->nullable()->constrained('employees');
        $table->string('product_name_snapshot', 150);
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->decimal('unit_price', 24, 4);
        $table->decimal('quantity', 24, 6);
        $table->decimal('recorded_quantity', 24, 6)->default(0);
        $table->decimal('quantity_variance', 24, 6)->default(0);
        $table->decimal('line_total', 24, 4);
        $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
        $table->timestamps();
    });

    Schema::create('shift_closing_summaries', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_closing_id')->unique()->constrained('shift_closings')->cascadeOnDelete();
        $table->decimal('fuel_sales', 24, 4)->default(0);
        $table->decimal('other_product_sales', 24, 4)->default(0);
        $table->decimal('credit_sales', 24, 4)->default(0);
        $table->decimal('bank_sales', 24, 4)->default(0);
        $table->decimal('cash_sales', 24, 4)->default(0);
        $table->decimal('cash_receipts', 24, 4)->default(0);
        $table->decimal('bank_receipts', 24, 4)->default(0);
        $table->decimal('cash_payments', 24, 4)->default(0);
        $table->decimal('bank_payments', 24, 4)->default(0);
        $table->decimal('office_payments', 24, 4)->default(0);
        $table->decimal('expected_cash', 24, 4)->default(0);
        $table->decimal('actual_cash', 24, 4)->default(0);
        $table->decimal('variance_amount', 24, 4)->default(0);
        $table->timestamp('refreshed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id')->nullable();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->string('sale_type', 30)->default('regular');
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales');
        $table->foreignId('product_id')->constrained('products');
        $table->foreignId('category_id')->nullable()->constrained('categories');
        $table->decimal('quantity', 24, 6)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales');
        $table->foreignId('account_id')->nullable()->constrained('accounts');
        $table->string('payment_method', 30)->default('cash');
        $table->decimal('amount', 24, 4)->default(0);
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
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id')->constrained('credit_sale_customers');
        $table->foreignId('product_id')->constrained('products');
        $table->foreignId('category_id')->nullable()->constrained('categories');
        $table->decimal('quantity', 24, 6)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('voucher_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 50)->unique();
        $table->string('name', 100);
        $table->string('voucher_type', 20)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->string('voucher_no', 100)->unique();
        $table->string('voucher_type', 20);
        $table->date('voucher_date');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('status', 20)->default('posted');
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id')->constrained('vouchers');
        $table->foreignId('account_id')->constrained('accounts');
        $table->string('entry_side', 10);
        $table->decimal('amount', 24, 4);
        $table->unsignedSmallInteger('line_no')->default(1);
        $table->timestamps();
    });
});

function setupShiftClosingEnvironment(): array
{
    $admin = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin@ewfs.test',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($admin);

    // Groups
    $cashGroup = Group::query()->create([
        'code' => AccountGroupHelper::code('cash_in_hand'),
        'name' => 'Cash in Hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    $revenueGroup = Group::query()->create([
        'code' => '3001',
        'name' => 'Sales Revenue',
        'account_class' => 'revenue',
        'normal_balance' => 'credit',
    ]);

    $expenseGroup = Group::query()->create([
        'code' => '4001',
        'name' => 'Cost of Goods Sold Group',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);

    $inventoryGroup = Group::query()->create([
        'code' => '1005',
        'name' => 'Inventory Asset Group',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);

    // Accounts
    $cashAccount = Account::query()->create([
        'name' => 'Office Cash',
        'ac_number' => 'CASH-01',
        'semantic_code' => 'cash_on_hand',
        'group_id' => $cashGroup->id,
        'status' => true,
    ]);

    $salesRevenue = Account::query()->create([
        'name' => 'Sales Revenue',
        'ac_number' => 'REV-01',
        'semantic_code' => 'sales_revenue',
        'group_id' => $revenueGroup->id,
        'status' => true,
    ]);

    $cogsAccount = Account::query()->create([
        'name' => 'Cost of Goods Sold',
        'ac_number' => 'COGS-01',
        'semantic_code' => 'cost_of_goods_sold',
        'group_id' => $expenseGroup->id,
        'status' => true,
    ]);

    $inventoryAsset = Account::query()->create([
        'name' => 'Inventory Asset',
        'ac_number' => 'INV-01',
        'semantic_code' => 'inventory_asset',
        'group_id' => $inventoryGroup->id,
        'status' => true,
    ]);

    $inventoryAdjustment = Account::query()->create([
        'name' => 'Inventory Adjustment',
        'ac_number' => 'ADJ-01',
        'semantic_code' => 'inventory_adjustment',
        'group_id' => $expenseGroup->id,
        'status' => true,
    ]);

    // Shift
    $shift = Shift::query()->create([
        'name' => 'Morning Shift',
        'start_time' => '06:00:00',
        'end_time' => '14:00:00',
        'status' => true,
    ]);

    // Fuel Category & Unit
    $fuelCategory = Category::query()->create([
        'name' => 'Octane Category',
        'code' => ErpHelper::dispenserProductCategoryCodes()[0] ?? 'OCT',
        'category_type' => 'fuel',
        'status' => true,
    ]);

    $literUnit = Unit::query()->create([
        'name' => 'Liter',
        'code' => 'LTR',
        'status' => true,
    ]);

    // Fuel Product
    $octane = Product::query()->create([
        'category_id' => $fuelCategory->id,
        'unit_id' => $literUnit->id,
        'product_name' => 'Octane 95',
        'product_code' => 'OCT-95',
        'is_inventory_item' => true,
        'status' => true,
    ]);

    ProductRate::query()->create([
        'product_id' => $octane->id,
        'sales_price' => 130.00,
        'purchase_price' => 120.00,
        'effective_date' => '2026-01-01',
        'status' => true,
    ]);

    Stock::query()->create([
        'product_id' => $octane->id,
        'opening_stock' => 10000.00,
        'current_stock' => 10000.00,
        'available_stock' => 10000.00,
    ]);

    // Dispenser
    $dispenser = Dispenser::query()->create([
        'dispenser_name' => 'Dispenser 01',
        'product_id' => $octane->id,
        'dispenser_item' => 'Nozzle 1',
        'opening_reading' => 1000.00,
        'status' => true,
    ]);

    $employee = Employee::query()->create([
        'employee_name' => 'Rahim Uddin',
        'employee_code' => 'EMP-001',
        'status' => true,
    ]);

    return compact(
        'admin',
        'cashAccount',
        'salesRevenue',
        'cogsAccount',
        'inventoryAsset',
        'inventoryAdjustment',
        'shift',
        'fuelCategory',
        'literUnit',
        'octane',
        'dispenser',
        'employee'
    );
}

it('Test Case 1: saves Dispenser Calculation with empty/null Reading By and stores NULL in database', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);

    $closing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1100.00,
                'meter_test' => 0.00,
                'reading_by' => null, // OMITTED / NULL
            ],
        ],
        'other_product_sales' => [],
    ]);

    expect($closing->status)->toBe('posted')
        ->and($closing->dispenserReadings)->toHaveCount(1);

    $reading = $closing->dispenserReadings->first();
    expect($reading->employee_id)->toBeNull();

    // Verify detailed shape
    $reading->load('employee');
    expect($reading->employee)->toBeNull();
});

it('Test Case 2: closes a shift with no unrecorded variance and reverses it cleanly', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);

    $closing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1000.00, // 0 sale, no variance
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    expect($closing->status)->toBe('posted');
    expect(ShiftClosing::query()->posted()->count())->toBe(1);

    // Reverse the shift closing
    $closingService->reverse($closing);

    $closing->refresh();
    expect($closing->status)->toBe('reversed')
        ->and($closing->reversed_at)->not->toBeNull()
        ->and(ShiftClosing::query()->posted()->count())->toBe(0);

    // Verify shift can be closed again on the same date
    $newClosing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1050.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    expect($newClosing->status)->toBe('posted')
        ->and(ShiftClosing::query()->posted()->count())->toBe(1);
});

it('Test Case 3: closed shift with cash deposit reverses journal entries and reconciles Cash Book', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);
    $ledgerQuery = app(LedgerQueryService::class);

    // Initial Cash on Hand = 0
    $initialLedger = $ledgerQuery->accountLedger($env['cashAccount'], '2026-08-01', '2026-08-31');
    expect($initialLedger['closing_balance'])->toBe(0.00);

    // Close Shift: 100 Liters @ 130 = 13,000 unrecorded cash fuel sales
    $closing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1100.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    expect($closing->journal_entry_id)->not->toBeNull();

    // Verify Cash Book shows +13,000
    $postedLedger = $ledgerQuery->accountLedger($env['cashAccount'], '2026-08-01', '2026-08-31');
    expect($postedLedger['closing_balance'])->toBe(13000.00)
        ->and($postedLedger['total_debit'])->toBe(13000.00);

    // Delete / Reverse the Shift Closing
    $closingService->reverse($closing);

    $closing->refresh();
    expect($closing->status)->toBe('reversed');

    // Original Journal Entry is marked 'reversed'
    $originalJournal = JournalEntry::query()->findOrFail($closing->journal_entry_id);
    expect($originalJournal->status)->toBe('reversed');

    // Reversal journal entry exists
    $reversalJournal = JournalEntry::query()->where('reversal_of_id', $originalJournal->id)->first();
    expect($reversalJournal)->not->toBeNull()
        ->and($reversalJournal->status)->toBe('posted');

    // Reconciled Cash Book: Net balance is back to 0.00 (13,000 Dr - 13,000 Cr)
    $reversedLedger = $ledgerQuery->accountLedger($env['cashAccount'], '2026-08-01', '2026-08-31');
    expect($reversedLedger['closing_balance'])->toBe(0.00)
        ->and($reversedLedger['total_debit'])->toBe(13000.00)
        ->and($reversedLedger['total_credit'])->toBe(13000.00);
});

it('Test Case 4: closed shift with inventory movements reverses inventory and cost lines', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);

    // Close Shift with 50 Liters fuel issue
    $closing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1050.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    // Check inventory movements
    $movements = InventoryMovement::query()
        ->where('source_type', ShiftClosing::class)
        ->where('source_id', $closing->id)
        ->get();

    expect($movements)->toHaveCount(1)
        ->and((float) $movements->first()->quantity_out)->toBe(50.00);

    // Reverse closing
    $closingService->reverse($closing);

    // Check reversal movements
    $allMovements = InventoryMovement::query()
        ->where('source_type', ShiftClosing::class)
        ->where('source_id', $closing->id)
        ->get();

    // 1 original out + 1 reversal in
    expect($allMovements)->toHaveCount(2);

    $reversal = $allMovements->firstWhere('reversal_of_id', $movements->first()->id);
    expect($reversal)->not->toBeNull()
        ->and((float) $reversal->quantity_in)->toBe(50.00);
});

it('Test Case 5: handles bulk delete / reverse of closed shifts', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);

    $shift2 = Shift::query()->create([
        'name' => 'Evening Shift',
        'start_time' => '14:00:00',
        'end_time' => '22:00:00',
        'status' => true,
    ]);

    $closing1 = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1020.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    $closing2 = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shift2->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1020.00,
                'end_reading' => 1040.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    expect(ShiftClosing::query()->posted()->count())->toBe(2);

    // Bulk reverse
    $closings = ShiftClosing::query()->whereIn('id', [$closing1->id, $closing2->id])->get();
    foreach ($closings as $closing) {
        $closingService->reverse($closing);
    }

    expect(ShiftClosing::query()->posted()->count())->toBe(0)
        ->and(ShiftClosing::query()->where('status', 'reversed')->count())->toBe(2);
});

it('Test Case 6: prevents reversing already reversed or invalid status shift closings', function (): void {
    $env = setupShiftClosingEnvironment();
    $closingService = app(ShiftClosingService::class);

    $closing = $closingService->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $env['shift']->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $env['dispenser']->id,
                'product_id' => $env['octane']->id,
                'start_reading' => 1000.00,
                'end_reading' => 1010.00,
                'meter_test' => 0.00,
                'reading_by' => null,
            ],
        ],
        'other_product_sales' => [],
    ]);

    // Reverse once: succeeds
    $closingService->reverse($closing);
    expect($closing->fresh()->status)->toBe('reversed');

    // Reverse again: safe no-op
    $closingService->reverse($closing->fresh());
    expect($closing->fresh()->status)->toBe('reversed');
});
