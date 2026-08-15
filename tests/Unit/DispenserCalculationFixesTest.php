<?php

use App\Helpers\ErpHelper;
use App\Models\Category;
use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Product;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Services\DailyStatementReportService;
use App\Services\DispenserCalculationService;
use App\Services\InventoryService;
use App\Services\ShiftClosingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('code', 32)->unique();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->unsignedTinyInteger('quantity_scale')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id');
        $table->foreignId('unit_id');
        $table->string('product_code', 64)->nullable();
        $table->string('product_name', 150);
        $table->boolean('is_inventory_item')->default(true);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('stocks', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id')->unique();
        $table->decimal('opening_stock', 24, 6)->default(0);
        $table->decimal('current_stock', 24, 6)->default(0);
        $table->decimal('reserved_stock', 24, 6)->default(0);
        $table->decimal('available_stock', 24, 6)->default(0);
        $table->decimal('minimum_stock', 24, 6)->default(0);
        $table->unsignedBigInteger('version')->default(0);
        $table->unsignedBigInteger('last_movement_id')->nullable();
        $table->timestamp('refreshed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('inventory_movements', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id');
        $table->foreignId('shift_id')->nullable();
        $table->unsignedBigInteger('journal_entry_id')->nullable();
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('movement_type', 50);
        $table->decimal('quantity_in', 24, 6)->default(0);
        $table->decimal('quantity_out', 24, 6)->default(0);
        $table->decimal('before_stock', 24, 6)->default(0);
        $table->decimal('after_stock', 24, 6)->default(0);
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('total_cost', 24, 6)->default(0);
        $table->string('source_type', 100);
        $table->unsignedBigInteger('source_id')->nullable();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key', 150)->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('shifts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->string('entry_no', 64)->unique();
        $table->string('status', 20)->default('posted');
        $table->timestamps();
    });

    Schema::create('journal_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->string('payment_method', 30)->nullable();
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_id');
        $table->foreignId('journal_entry_id');
        $table->date('sale_date');
        $table->string('sale_type', 30)->default('regular');
        $table->string('status', 20)->default('posted');
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('product_id');
        $table->foreignId('category_id');
        $table->string('product_name_snapshot', 150);
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_id')->nullable();
        $table->date('sale_date');
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_id');
        $table->foreignId('journal_entry_id');
        $table->string('customer_name_snapshot', 150)->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->foreignId('product_id');
        $table->foreignId('category_id')->nullable();
        $table->string('vehicle_number_snapshot', 50)->nullable();
        $table->string('product_name_snapshot', 150)->nullable();
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->decimal('quantity', 24, 6)->default(0);
        $table->decimal('unit_price', 24, 6)->default(0);
        $table->decimal('line_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id')->unique();
        $table->unsignedBigInteger('account_id')->nullable();
        $table->string('payment_method', 30);
        $table->timestamps();
    });

    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 150);
        $table->string('code', 64)->unique();
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 150);
        $table->foreignId('group_id');
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->string('voucher_type', 20);
        $table->date('voucher_date');
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('journal_entry_id');
        $table->string('status', 20)->default('posted');
        $table->text('description')->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id');
        $table->foreignId('account_id');
        $table->enum('entry_side', ['debit', 'credit']);
        $table->decimal('amount', 24, 4);
        $table->timestamps();
    });

    Schema::create('voucher_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_line_id')->unique();
        $table->string('payment_method', 30);
        $table->timestamps();
    });
});

it('correctly calculates bank sales using sale_payment_details with journal_lines fallback', function (): void {
    $gasCategory = Category::query()->create(['name' => 'Gas', 'code' => '1002']);
    $unit = DB::table('units')->insertGetId(['name' => 'Liter']);
    $fuelProduct = Product::query()->create([
        'category_id' => $gasCategory->id,
        'unit_id' => $unit,
        'product_name' => 'Octane',
    ]);
    $shift = Shift::query()->create(['name' => 'Shift 1']);

    $journal1 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-1', 'status' => 'posted']);
    $sale1 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $journal1,
        'sale_date' => '2026-08-15',
        'sale_type' => 'regular',
        'status' => 'posted',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $sale1,
        'product_id' => $fuelProduct->id,
        'category_id' => $gasCategory->id,
        'product_name_snapshot' => 'Octane',
        'quantity' => 10,
        'unit_price' => 130,
        'line_total' => 1300,
    ]);
    DB::table('sale_payment_details')->insert([
        'sale_id' => $sale1,
        'payment_method' => 'bank',
    ]);

    $journal2 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-2', 'status' => 'posted']);
    DB::table('journal_lines')->insert([
        'journal_entry_id' => $journal2,
        'debit_amount' => 650,
        'credit_amount' => 0,
        'payment_method' => 'mobile_bank',
    ]);
    $sale2 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $journal2,
        'sale_date' => '2026-08-15',
        'sale_type' => 'regular',
        'status' => 'posted',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $sale2,
        'product_id' => $fuelProduct->id,
        'category_id' => $gasCategory->id,
        'product_name_snapshot' => 'Octane',
        'quantity' => 5,
        'unit_price' => 130,
        'line_total' => 650,
    ]);

    $shiftClosingService = app(ShiftClosingService::class);
    $reflection = new ReflectionClass($shiftClosingService);
    $method = $reflection->getMethod('bankSales');
    $method->setAccessible(true);

    $bankFuelSales = $method->invoke($shiftClosingService, '2026-08-15', $shift->id, true);

    expect((float) $bankFuelSales)->toBe(1950.00);
});

it('avoids voucher line multiplication in DailyStatementReportService voucherRows', function (): void {
    $group = DB::table('groups')->insertGetId(['name' => 'Expense', 'code' => 'EXP']);
    $account = DB::table('accounts')->insertGetId(['name' => 'Supplier Account', 'group_id' => $group]);
    $shift = Shift::query()->create(['name' => 'Shift 1']);
    $journal = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-V1', 'status' => 'posted']);

    $voucher = DB::table('vouchers')->insertGetId([
        'voucher_type' => 'payment',
        'voucher_date' => '2026-08-15',
        'shift_id' => $shift->id,
        'journal_entry_id' => $journal,
        'status' => 'posted',
        'description' => 'Office equipment purchase',
    ]);

    $debitLine1 = DB::table('voucher_lines')->insertGetId([
        'voucher_id' => $voucher,
        'account_id' => $account,
        'entry_side' => 'debit',
        'amount' => 5000,
    ]);
    $debitLine2 = DB::table('voucher_lines')->insertGetId([
        'voucher_id' => $voucher,
        'account_id' => $account,
        'entry_side' => 'debit',
        'amount' => 2000,
    ]);

    DB::table('voucher_payment_details')->insert([
        'voucher_line_id' => $debitLine1,
        'payment_method' => 'bank',
    ]);

    $reportService = app(DailyStatementReportService::class);
    $report = $reportService->report('2026-08-15', '2026-08-15', $shift->id);

    $cashPaymentRows = $report['cashPayment'];
    expect($cashPaymentRows)->toHaveCount(2)
        ->and((float) $cashPaymentRows->sum('amount'))->toBe(7000.00);
});
