<?php

use App\Models\Account;
use App\Models\Group;
use App\Models\Category;
use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Stock;
use App\Models\Unit;
use App\Services\AccountingService;
use App\Services\DispenserCalculationService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\ShiftClosingService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
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
    Schema::dropIfExists('inventory_movements');
    Schema::dropIfExists('journal_lines');
    Schema::dropIfExists('journal_entries');
    Schema::dropIfExists('dispenser_readings');
    Schema::dropIfExists('shift_closing_product_items');
    Schema::dropIfExists('shift_closings');
    Schema::dropIfExists('dispensers');
    Schema::dropIfExists('product_rates');
    Schema::dropIfExists('stocks');
    Schema::dropIfExists('products');
    Schema::dropIfExists('categories');
    Schema::dropIfExists('units');
    Schema::dropIfExists('shifts');
    Schema::dropIfExists('employees');
    Schema::dropIfExists('accounts');
    Schema::dropIfExists('account_groups');
    Schema::dropIfExists('accounting_periods');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('sale_items');
    Schema::dropIfExists('credit_sales');
    Schema::dropIfExists('credit_sale_customers');
    Schema::dropIfExists('credit_sale_items');
    Schema::dropIfExists('vouchers');
    Schema::dropIfExists('voucher_lines');

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

    Schema::create('accounting_periods', function (Blueprint $table) {
        $table->id();
        $table->date('starts_on');
        $table->date('ends_on');
        $table->string('status')->default('open');
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('accounting_period_id')->nullable();
        $table->foreignId('shift_id')->nullable();
        $table->string('entry_no')->nullable();
        $table->date('business_date');
        $table->dateTime('occurred_at');
        $table->string('event_type');
        $table->string('source_type')->nullable();
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('draft');
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->unsignedBigInteger('reversed_by')->nullable();
        $table->dateTime('reversed_at')->nullable();
        $table->string('idempotency_key')->nullable();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->dateTime('posted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('journal_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->unsignedInteger('line_no');
        $table->foreignId('account_id');
        $table->decimal('debit_amount', 18, 4)->default(0);
        $table->decimal('credit_amount', 18, 4)->default(0);
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
    });

    Schema::create('inventory_movements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->date('business_date');
        $table->timestamp('occurred_at')->nullable();
        $table->string('movement_type');
        $table->decimal('quantity_in', 18, 4)->default(0);
        $table->decimal('quantity_out', 18, 4)->default(0);
        $table->decimal('unit_cost', 18, 4)->default(0);
        $table->decimal('total_cost', 18, 4)->default(0);
        $table->string('source_type')->nullable();
        $table->unsignedBigInteger('source_id')->nullable();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key')->nullable();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->decimal('before_stock', 24, 6)->nullable();
        $table->decimal('after_stock', 24, 6)->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('unit_id')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->string('product_name');
        $table->boolean('is_inventory_item')->default(true);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('product_rates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->decimal('sales_price', 18, 4);
        $table->decimal('purchase_price', 18, 4)->default(0);
        $table->date('effective_date')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('stocks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->unique();
        $table->decimal('opening_stock', 18, 4)->default(0);
        $table->decimal('current_stock', 18, 4)->default(0);
        $table->decimal('available_stock', 18, 4)->default(0);
        $table->decimal('reserved_stock', 18, 4)->default(0);
        $table->decimal('minimum_stock', 18, 4)->default(0);
        $table->decimal('maximum_stock', 18, 4)->nullable();
        $table->decimal('unit_price', 18, 4)->default(0);
        $table->unsignedBigInteger('last_movement_id')->nullable();
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamp('refreshed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('shifts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('employee_name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('dispensers', function (Blueprint $table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('dispenser_name');
        $table->foreignId('product_id');
        $table->string('dispenser_item')->nullable();
        $table->decimal('opening_reading', 24, 6)->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('shift_closings', function (Blueprint $table) {
        $table->id();
        $table->date('business_date');
        $table->foreignId('shift_id');
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('status')->default('draft');
        $table->decimal('expected_cash', 18, 4)->default(0);
        $table->decimal('actual_cash', 18, 4)->default(0);
        $table->decimal('variance_amount', 18, 4)->default(0);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('closed_by')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->unsignedBigInteger('reversed_by')->nullable();
        $table->dateTime('reversed_at')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->unsignedBigInteger('lock_version')->default(0);
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('dispenser_readings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shift_closing_id');
        $table->foreignId('dispenser_id');
        $table->foreignId('product_id');
        $table->foreignId('employee_id')->nullable();
        $table->decimal('start_reading', 24, 6);
        $table->decimal('end_reading', 24, 6);
        $table->decimal('meter_test', 24, 6)->default(0);
        $table->decimal('net_quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('gross_amount', 18, 4);
        $table->unsignedBigInteger('inventory_movement_id')->nullable();
        $table->timestamps();
    });

    Schema::create('shift_closing_product_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shift_closing_id');
        $table->foreignId('product_id');
        $table->foreignId('unit_id')->nullable();
        $table->foreignId('employee_id')->nullable();
        $table->string('product_name_snapshot')->nullable();
        $table->string('unit_name_snapshot')->nullable();
        $table->decimal('unit_price', 18, 4);
        $table->decimal('quantity', 18, 4);
        $table->decimal('recorded_quantity', 18, 4)->default(0);
        $table->decimal('quantity_variance', 18, 4)->default(0);
        $table->decimal('line_total', 18, 4);
        $table->timestamps();
    });

    Schema::create('shift_closing_summaries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shift_closing_id');
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
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_entry_id')->nullable();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->string('sale_type', 30)->default('regular');
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('product_id');
        $table->foreignId('category_id')->nullable();
        $table->decimal('quantity', 24, 6)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('account_id')->nullable();
        $table->string('payment_method', 30)->default('cash');
        $table->decimal('amount', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table) {
        $table->id();
        $table->date('sale_date');
        $table->foreignId('shift_id')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_id');
        $table->foreignId('journal_entry_id')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->foreignId('product_id');
        $table->foreignId('category_id')->nullable();
        $table->decimal('quantity', 24, 6)->default(0);
        $table->decimal('unit_price', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('code', 50)->unique();
        $table->string('name', 100);
        $table->string('voucher_type', 20)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->string('voucher_no')->nullable();
        $table->string('voucher_type', 30);
        $table->date('voucher_date');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('status', 30)->default('posted');
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_id');
        $table->foreignId('account_id');
        $table->string('entry_side', 10);
        $table->decimal('amount', 24, 4);
        $table->timestamps();
    });

    // Seed base accounts and groups
    $assetGroup = Group::query()->create([
        'code' => '100020002',
        'name' => 'Cash in Hand',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);
    $invGroup = Group::query()->create([
        'code' => '10003',
        'name' => 'Inventory',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ]);
    $revGroup = Group::query()->create([
        'code' => '40001',
        'name' => 'Sales Revenue',
        'account_class' => 'revenue',
        'normal_balance' => 'credit',
    ]);
    $cogsGroup = Group::query()->create([
        'code' => '50001',
        'name' => 'Cost of Goods Sold',
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ]);

    Account::query()->create(['name' => 'Cash Account', 'group_id' => $assetGroup->id, 'semantic_code' => 'cash_on_hand']);
    Account::query()->create(['name' => 'Inventory Account', 'group_id' => $invGroup->id, 'semantic_code' => 'inventory']);
    Account::query()->create(['name' => 'Fuel Sales Revenue', 'group_id' => $revGroup->id, 'semantic_code' => 'sales_revenue']);
    Account::query()->create(['name' => 'Fuel COGS', 'group_id' => $cogsGroup->id, 'semantic_code' => 'cost_of_goods_sold']);

    DB::table('accounting_periods')->insert([
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('Test 1: Single shift close and full transactional reversal', function () {
    $unit = Unit::query()->create(['name' => 'Litre']);
    $cat = Category::query()->create(['code' => \App\Helpers\ErpHelper::dispenserProductCategoryCodes()[0], 'name' => 'Oil']);
    $octane = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Octane', 'is_inventory_item' => true]);
    ProductRate::query()->create(['product_id' => $octane->id, 'sales_price' => 100.00, 'purchase_price' => 80.00, 'effective_date' => '2026-01-01', 'status' => true]);
    Stock::query()->updateOrCreate(['product_id' => $octane->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 80.00]);

    $shift = Shift::query()->create(['name' => 'Morning Shift', 'status' => true]);
    $dispenser = Dispenser::query()->create([
        'dispenser_name' => 'Dispenser #1',
        'product_id' => $octane->id,
        'opening_reading' => 1000.00,
        'status' => true,
    ]);

    $service = app(ShiftClosingService::class);

    // Close Shift 1: Old 1000 -> New 1500 (Net 500 @ 100 = 50,000)
    $closing = $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shift->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $dispenser->id,
                'product_id' => $octane->id,
                'start_reading' => 1000.00,
                'end_reading' => 1500.00,
                'meter_test' => 0,
            ],
        ],
    ]);

    expect($closing->status)->toBe('posted')
        ->and((float) $closing->dispenserReadings->first()->net_quantity)->toBe(500.0)
        ->and((float) $closing->dispenserReadings->first()->gross_amount)->toBe(50000.0);

    // Check stock was reduced by 500
    $stock = Stock::query()->where('product_id', $octane->id)->first();
    expect((float) $stock->current_stock)->toBe(9500.0);

    // Check Dispenser latestReading is now 1500
    $dispenser->refresh();
    expect((float) $dispenser->latestReading->end_reading)->toBe(1500.0);

    // REVERSE Shift 1
    $service->reverse($closing);

    $closing->refresh();
    expect($closing->status)->toBe('reversed');

    // Check stock is restored to 10000
    $stock->refresh();
    expect((float) $stock->current_stock)->toBe(10000.0);

    // Check Dispenser latestReading falls back to opening reading (1000)
    $dispenser->refresh();
    expect($dispenser->latestReading)->toBeNull()
        ->and((float) ($dispenser->latestReading?->end_reading ?? $dispenser->opening_reading))->toBe(1000.0);
});

test('Test 2: Sequential shifts and reversing latest shift restores previous shift reading', function () {
    $unit = Unit::query()->create(['name' => 'Litre']);
    $cat = Category::query()->create(['code' => \App\Helpers\ErpHelper::dispenserProductCategoryCodes()[0], 'name' => 'Oil']);
    $diesel = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Diesel', 'is_inventory_item' => true]);
    ProductRate::query()->create(['product_id' => $diesel->id, 'sales_price' => 105.00, 'purchase_price' => 85.00, 'effective_date' => '2026-01-01', 'status' => true]);
    Stock::query()->updateOrCreate(['product_id' => $diesel->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 85.00]);

    $shiftA = Shift::query()->create(['name' => 'Shift A', 'status' => true]);
    $shiftB = Shift::query()->create(['name' => 'Shift B', 'status' => true]);
    $dispenser = Dispenser::query()->create([
        'dispenser_name' => 'Dispenser #2',
        'product_id' => $diesel->id,
        'opening_reading' => 1000.00,
        'status' => true,
    ]);

    $service = app(ShiftClosingService::class);

    // Shift A: 1000 -> 1500
    $closingA = $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shiftA->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $dispenser->id,
                'product_id' => $diesel->id,
                'start_reading' => 1000.00,
                'end_reading' => 1500.00,
                'meter_test' => 0,
            ],
        ],
    ]);

    $dispenser->refresh();
    expect((float) $dispenser->latestReading->end_reading)->toBe(1500.0);

    // Shift B: 1500 -> 1800
    $closingB = $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shiftB->id,
        'dispenser_readings' => [
            [
                'dispenser_id' => $dispenser->id,
                'product_id' => $diesel->id,
                'start_reading' => 1500.00,
                'end_reading' => 1800.00,
                'meter_test' => 0,
            ],
        ],
    ]);

    $dispenser->refresh();
    expect((float) $dispenser->latestReading->end_reading)->toBe(1800.0);

    // Reverse Shift B
    $service->reverse($closingB);

    // Reading chain must safely point to Shift A (1500)
    $dispenser->refresh();
    expect((float) $dispenser->latestReading->end_reading)->toBe(1500.0);
});

test('Test 3: Dependent shift protection blocks deleting non-terminal shift', function () {
    $unit = Unit::query()->create(['name' => 'Litre']);
    $cat = Category::query()->create(['code' => \App\Helpers\ErpHelper::dispenserProductCategoryCodes()[0], 'name' => 'Oil']);
    $octane = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Octane', 'is_inventory_item' => true]);
    ProductRate::query()->create(['product_id' => $octane->id, 'sales_price' => 125.00, 'purchase_price' => 100.00, 'effective_date' => '2026-01-01', 'status' => true]);
    Stock::query()->updateOrCreate(['product_id' => $octane->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 100.00]);

    $shiftA = Shift::query()->create(['name' => 'Shift 1', 'status' => true]);
    $shiftB = Shift::query()->create(['name' => 'Shift 2', 'status' => true]);
    $shiftC = Shift::query()->create(['name' => 'Shift 3', 'status' => true]);

    $dispenser = Dispenser::query()->create([
        'dispenser_name' => 'Dispenser #1',
        'product_id' => $octane->id,
        'opening_reading' => 1000.00,
        'status' => true,
    ]);

    $service = app(ShiftClosingService::class);

    // Close Shift A (1000 -> 1500 on 2026-08-15)
    $closingA = $service->close([
        'transaction_date' => '2026-08-15',
        'shift_id' => $shiftA->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispenser->id, 'product_id' => $octane->id, 'start_reading' => 1000.00, 'end_reading' => 1500.00],
        ],
    ]);

    // Close Shift B (1500 -> 1800 on 2026-08-16)
    $closingB = $service->close([
        'transaction_date' => '2026-08-16',
        'shift_id' => $shiftB->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispenser->id, 'product_id' => $octane->id, 'start_reading' => 1500.00, 'end_reading' => 1800.00],
        ],
    ]);

    // Close Shift C (1800 -> 2200 on 2026-08-17)
    $closingC = $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shiftC->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispenser->id, 'product_id' => $octane->id, 'start_reading' => 1800.00, 'end_reading' => 2200.00],
        ],
    ]);

    // Attempt to reverse Shift B while Shift C is active -> MUST THROW EXCEPTION
    expect(fn () => $service->reverse($closingB))->toThrow(ValidationException::class);

    // Reversing Shift C first (LIFO) -> SUCCEEDS
    $service->reverse($closingC);
    expect($closingC->refresh()->status)->toBe('reversed');

    // Now reversing Shift B -> SUCCEEDS
    $service->reverse($closingB);
    expect($closingB->refresh()->status)->toBe('reversed');

    // Reading chain safely falls back to Shift A (1500)
    $dispenser->refresh();
    expect((float) $dispenser->latestReading->end_reading)->toBe(1500.0);
});

test('Test 4: Shift with multiple dispensers reverses all dispensers atomically', function () {
    $unit = Unit::query()->create(['name' => 'Litre']);
    $cat = Category::query()->create(['code' => \App\Helpers\ErpHelper::dispenserProductCategoryCodes()[0], 'name' => 'Oil']);
    $octane = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Octane', 'is_inventory_item' => true]);
    $diesel = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Diesel', 'is_inventory_item' => true]);

    ProductRate::query()->create(['product_id' => $octane->id, 'sales_price' => 125.00, 'purchase_price' => 100.00, 'effective_date' => '2026-01-01', 'status' => true]);
    ProductRate::query()->create(['product_id' => $diesel->id, 'sales_price' => 105.00, 'purchase_price' => 85.00, 'effective_date' => '2026-01-01', 'status' => true]);

    Stock::query()->updateOrCreate(['product_id' => $octane->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 100.00]);
    Stock::query()->updateOrCreate(['product_id' => $diesel->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 85.00]);

    $shift = Shift::query()->create(['name' => 'Day Shift', 'status' => true]);

    $dispensers = [];
    for ($i = 1; $i <= 3; $i++) {
        $dispensers[] = Dispenser::query()->create([
            'dispenser_name' => "Dispenser #{$i}",
            'product_id' => $i === 1 ? $octane->id : $diesel->id,
            'opening_reading' => 1000.00 * $i,
            'status' => true,
        ]);
    }

    $service = app(ShiftClosingService::class);

    $closing = $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shift->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispensers[0]->id, 'product_id' => $octane->id, 'start_reading' => 1000.00, 'end_reading' => 1200.00, 'meter_test' => 10], // Net 190
            ['dispenser_id' => $dispensers[1]->id, 'product_id' => $diesel->id, 'start_reading' => 2000.00, 'end_reading' => 2300.00, 'meter_test' => 0],  // Net 300
            ['dispenser_id' => $dispensers[2]->id, 'product_id' => $diesel->id, 'start_reading' => 3000.00, 'end_reading' => 3150.00, 'meter_test' => 0],  // Net 150
        ],
    ]);

    expect($closing->dispenserReadings)->toHaveCount(3);

    // Reverse shift
    $service->reverse($closing);

    // Check all dispensers are restored
    foreach ($dispensers as $d) {
        $d->refresh();
        expect($d->latestReading)->toBeNull();
    }
});

test('Test 5: Validation blocks New Reading < Old Reading and excessive Meter Test', function () {
    $unit = Unit::query()->create(['name' => 'Litre']);
    $cat = Category::query()->create(['code' => \App\Helpers\ErpHelper::dispenserProductCategoryCodes()[0], 'name' => 'Oil']);
    $octane = Product::query()->create(['unit_id' => $unit->id, 'category_id' => $cat->id, 'product_name' => 'Octane', 'is_inventory_item' => true]);
    ProductRate::query()->create(['product_id' => $octane->id, 'sales_price' => 125.00, 'purchase_price' => 100.00, 'effective_date' => '2026-01-01', 'status' => true]);
    Stock::query()->updateOrCreate(['product_id' => $octane->id], ['opening_stock' => 10000, 'current_stock' => 10000, 'available_stock' => 10000, 'unit_price' => 100.00]);

    $shift = Shift::query()->create(['name' => 'Morning Shift', 'status' => true]);
    $dispenser = Dispenser::query()->create([
        'dispenser_name' => 'Dispenser #1',
        'product_id' => $octane->id,
        'opening_reading' => 1500.00,
        'status' => true,
    ]);

    $service = app(ShiftClosingService::class);

    // 1. End reading < Start reading (1400 < 1500)
    expect(fn () => $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shift->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispenser->id, 'product_id' => $octane->id, 'start_reading' => 1500.00, 'end_reading' => 1400.00],
        ],
    ]))->toThrow(ValidationException::class);

    // 2. Meter test > (End reading - Start reading) (test = 100, gross = 50)
    expect(fn () => $service->close([
        'transaction_date' => '2026-08-17',
        'shift_id' => $shift->id,
        'dispenser_readings' => [
            ['dispenser_id' => $dispenser->id, 'product_id' => $octane->id, 'start_reading' => 1500.00, 'end_reading' => 1550.00, 'meter_test' => 100.00],
        ],
    ]))->toThrow(ValidationException::class);
});
