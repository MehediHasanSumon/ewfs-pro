<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Services\DailyStatementReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('code', 32)->unique();
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->foreignId('group_id')->nullable();
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('code', 32)->unique();
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id');
        $table->foreignId('unit_id');
        $table->string('product_name', 150);
        $table->timestamps();
    });

    Schema::create('shifts', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
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
        $table->foreignId('account_id')->nullable();
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->string('payment_method', 30)->nullable();
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('journal_entry_id');
        $table->date('sale_date');
        $table->string('sale_type', 30)->default('regular');
        $table->string('status', 20)->default('paid');
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('account_id')->nullable();
        $table->string('payment_method', 30)->default('cash');
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('product_id');
        $table->foreignId('category_id')->nullable();
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
        $table->string('customer_name_snapshot', 150);
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->foreignId('product_id');
        $table->string('product_name_snapshot', 150);
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->string('vehicle_number_snapshot', 50)->nullable();
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->string('voucher_type', 30);
        $table->date('voucher_date');
        $table->foreignId('shift_id')->nullable();
        $table->string('status', 20)->default('posted');
        $table->string('description', 255)->nullable();
        $table->timestamps();
    });

    Schema::create('voucher_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_id');
        $table->foreignId('account_id');
        $table->string('entry_side', 10);
        $table->decimal('amount', 24, 4);
        $table->timestamps();
    });

    Schema::create('voucher_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('voucher_line_id');
        $table->string('payment_method', 30)->default('cash');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('voucher_payment_details');
    Schema::dropIfExists('voucher_lines');
    Schema::dropIfExists('vouchers');
    Schema::dropIfExists('credit_sale_items');
    Schema::dropIfExists('credit_sale_customers');
    Schema::dropIfExists('credit_sales');
    Schema::dropIfExists('sale_items');
    Schema::dropIfExists('sale_payment_details');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('journal_lines');
    Schema::dropIfExists('journal_entries');
    Schema::dropIfExists('shifts');
    Schema::dropIfExists('products');
    Schema::dropIfExists('units');
    Schema::dropIfExists('categories');
    Schema::dropIfExists('accounts');
    Schema::dropIfExists('groups');
});

it('calculates Cash Sales, Bank Sales, and Credit Sales with multiple products and single date filter', function (): void {
    $shift = Shift::query()->create(['name' => 'Morning Shift']);
    $cashGroup = DB::table('groups')->insertGetId(['name' => 'Cash in Hand', 'code' => '100020002']);
    $bankGroup = DB::table('groups')->insertGetId(['name' => 'Bank Account', 'code' => '100020004']);
    $cashAccount = DB::table('accounts')->insertGetId(['name' => 'Main Cash Account', 'group_id' => $cashGroup]);
    $bankAccount = DB::table('accounts')->insertGetId(['name' => 'City Bank Account', 'group_id' => $bankGroup]);

    $category = DB::table('categories')->insertGetId(['name' => 'Fuel', 'code' => '1001']);
    $unit = DB::table('units')->insertGetId(['name' => 'Litre']);
    $diesel = DB::table('products')->insertGetId(['product_name' => 'Diesel', 'category_id' => $category, 'unit_id' => $unit]);
    $petrol = DB::table('products')->insertGetId(['product_name' => 'Petrol', 'category_id' => $category, 'unit_id' => $unit]);
    $octane = DB::table('products')->insertGetId(['product_name' => 'Octane', 'category_id' => $category, 'unit_id' => $unit]);

    // 1. Regular Cash Sale on 2026-08-18 (Multiple Products: Diesel 10L @ 100 = 1,000, Petrol 5L @ 120 = 600) -> Total Cash 1,600
    $jCash = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-CS1', 'status' => 'posted']);
    $saleCash = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $jCash,
        'sale_date' => '2026-08-18',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert([
        'sale_id' => $saleCash,
        'account_id' => $cashAccount,
        'payment_method' => 'cash',
    ]);
    DB::table('sale_items')->insert([
        [
            'sale_id' => $saleCash,
            'product_id' => $diesel,
            'product_name_snapshot' => 'Diesel',
            'unit_name_snapshot' => 'Litre',
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
        ],
        [
            'sale_id' => $saleCash,
            'product_id' => $petrol,
            'product_name_snapshot' => 'Petrol',
            'unit_name_snapshot' => 'Litre',
            'quantity' => 5,
            'unit_price' => 120,
            'line_total' => 600,
        ],
    ]);

    // 2. White Sale on 2026-08-18 (Octane 4L @ 130 = 520) -> Cash Sale 520
    $jWhite = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-WS1', 'status' => 'posted']);
    $saleWhite = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $jWhite,
        'sale_date' => '2026-08-18',
        'sale_type' => 'white',
        'status' => 'paid',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $saleWhite,
        'product_id' => $octane,
        'product_name_snapshot' => 'Octane',
        'unit_name_snapshot' => 'Litre',
        'quantity' => 4,
        'unit_price' => 130,
        'line_total' => 520,
    ]);

    // 3. Bank Sale on 2026-08-18 (Octane 10L @ 130 = 1,300) -> Bank Sale 1,300
    $jBank = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-BS1', 'status' => 'posted']);
    $saleBank = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $jBank,
        'sale_date' => '2026-08-18',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert([
        'sale_id' => $saleBank,
        'account_id' => $bankAccount,
        'payment_method' => 'bank',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $saleBank,
        'product_id' => $octane,
        'product_name_snapshot' => 'Octane',
        'unit_name_snapshot' => 'Litre',
        'quantity' => 10,
        'unit_price' => 130,
        'line_total' => 1300,
    ]);

    // 4. Credit Sale on 2026-08-18 (Diesel 20L @ 100 = 2,000) -> Credit Sale 2,000
    $jCredit = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-CR1', 'status' => 'posted']);
    $creditSale = DB::table('credit_sales')->insertGetId([
        'shift_id' => $shift->id,
        'sale_date' => '2026-08-18',
    ]);
    $creditCust = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $creditSale,
        'journal_entry_id' => $jCredit,
        'customer_name_snapshot' => 'Acme Transport',
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditCust,
        'product_id' => $diesel,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'vehicle_number_snapshot' => 'DHAKA-METRO-1122',
        'quantity' => 20,
        'unit_price' => 100,
        'line_total' => 2000,
    ]);

    // 5. Customer Due Paid (Receipt Voucher) on 2026-08-18 = 500 (NOT SALES)
    $jReceipt = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-RC1', 'status' => 'posted']);
    $voucher = DB::table('vouchers')->insertGetId([
        'journal_entry_id' => $jReceipt,
        'voucher_type' => 'receipt',
        'voucher_date' => '2026-08-18',
        'shift_id' => $shift->id,
        'status' => 'posted',
        'description' => 'Customer Due Collection',
    ]);
    $debitVl = DB::table('voucher_lines')->insertGetId([
        'voucher_id' => $voucher,
        'account_id' => $cashAccount,
        'entry_side' => 'debit',
        'amount' => 500,
    ]);
    DB::table('voucher_payment_details')->insert([
        'voucher_line_id' => $debitVl,
        'payment_method' => 'cash',
    ]);
    DB::table('voucher_lines')->insert([
        'voucher_id' => $voucher,
        'account_id' => $cashAccount,
        'entry_side' => 'credit',
        'amount' => 500,
    ]);

    // 6. Cash Sale on other date: 2026-08-17 (Diesel 50L = 5,000)
    $jPrev = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-PREV', 'status' => 'posted']);
    $salePrev = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $jPrev,
        'sale_date' => '2026-08-17',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert([
        'sale_id' => $salePrev,
        'account_id' => $cashAccount,
        'payment_method' => 'cash',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $salePrev,
        'product_id' => $diesel,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'quantity' => 50,
        'unit_price' => 100,
        'line_total' => 5000,
    ]);

    // Execute Daily Statement report for single date: 2026-08-18
    $service = app(DailyStatementReportService::class);
    $report = $service->report('2026-08-18', $shift->id);

    // Verify Cash Sales: Diesel (1,000) + Petrol (600) + Octane White (520) = 2,120.00
    expect((float) $report['cashSales']->sum('total_amount'))->toBe(2120.00)
        ->and($report['cashSales'])->toHaveCount(3);

    // Verify Bank Sales: Octane (1,300) = 1,300.00
    expect((float) $report['bankSales']->sum('total_amount'))->toBe(1300.00)
        ->and($report['bankSales'])->toHaveCount(1);

    // Verify Credit Sales: Diesel (2,000) = 2,000.00
    expect((float) $report['creditSales']->sum('total_amount'))->toBe(2000.00)
        ->and($report['creditSales'])->toHaveCount(1);

    // Verify Combined Product Wise Sales: 2,120 + 1,300 + 2,000 = 5,420.00
    expect((float) $report['productWiseSales']->sum('total_amount'))->toBe(5420.00);

    // Verify that Customer Due Paid (500) appears in cashReceived and NOT in sales
    expect((float) $report['cashReceived']->sum('amount'))->toBe(500.00);

    // Verify that 2026-08-17 sale (5,000) is NOT included in 2026-08-18 report
    expect($report['productWiseSales']->where('product_name', 'Diesel')->first()['total_amount'])->toBe(3000.00); // 1,000 Cash + 2,000 Credit
});

it('strictly isolates dates: 2026-08-17, 2026-08-18, 2026-08-19', function (): void {
    $shift = Shift::query()->create(['name' => 'Morning Shift']);
    $cashGroup = DB::table('groups')->insertGetId(['name' => 'Cash in Hand', 'code' => '100020002']);
    $cashAccount = DB::table('accounts')->insertGetId(['name' => 'Main Cash Account', 'group_id' => $cashGroup]);
    $category = DB::table('categories')->insertGetId(['name' => 'Fuel', 'code' => '1001']);
    $unit = DB::table('units')->insertGetId(['name' => 'Litre']);
    $diesel = DB::table('products')->insertGetId(['product_name' => 'Diesel', 'category_id' => $category, 'unit_id' => $unit]);

    // 08/17 Cash Sale = 1,000
    $j17 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-17', 'status' => 'posted']);
    $sale17 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $j17,
        'sale_date' => '2026-08-17',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $sale17, 'account_id' => $cashAccount, 'payment_method' => 'cash']);
    DB::table('sale_items')->insert(['sale_id' => $sale17, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 10, 'unit_price' => 100, 'line_total' => 1000]);

    // 08/18 Cash Sale = 2,000
    $j18 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-18', 'status' => 'posted']);
    $sale18 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $j18,
        'sale_date' => '2026-08-18',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $sale18, 'account_id' => $cashAccount, 'payment_method' => 'cash']);
    DB::table('sale_items')->insert(['sale_id' => $sale18, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 20, 'unit_price' => 100, 'line_total' => 2000]);

    // 08/19 Cash Sale = 3,000
    $j19 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-19', 'status' => 'posted']);
    $sale19 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $j19,
        'sale_date' => '2026-08-19',
        'sale_type' => 'regular',
        'status' => 'paid',
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $sale19, 'account_id' => $cashAccount, 'payment_method' => 'cash']);
    DB::table('sale_items')->insert(['sale_id' => $sale19, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 30, 'unit_price' => 100, 'line_total' => 3000]);

    $service = app(DailyStatementReportService::class);

    // Selecting 2026-08-18 should return EXACTLY 2,000 (NOT 1,000, NOT 3,000, NOT 6,000)
    $report18 = $service->report('2026-08-18', $shift->id);
    expect((float) $report18['cashSales']->sum('total_amount'))->toBe(2000.00)
        ->and((float) $report18['productWiseSales']->sum('total_amount'))->toBe(2000.00);

    // Selecting 2026-08-17 should return EXACTLY 1,000
    $report17 = $service->report('2026-08-17', $shift->id);
    expect((float) $report17['cashSales']->sum('total_amount'))->toBe(1000.00);

    // Selecting 2026-08-19 should return EXACTLY 3,000
    $report19 = $service->report('2026-08-19', $shift->id);
    expect((float) $report19['cashSales']->sum('total_amount'))->toBe(3000.00);
});

it('handles zero data correctly when no transactions exist on date', function (): void {
    $service = app(DailyStatementReportService::class);
    $report = $service->report('2026-08-25');

    expect($report['cashSales'])->toHaveCount(0)
        ->and($report['bankSales'])->toHaveCount(0)
        ->and($report['creditSales'])->toHaveCount(0)
        ->and($report['productWiseSales'])->toHaveCount(0)
        ->and($report['cashReceived'])->toHaveCount(0)
        ->and($report['cashPayment'])->toHaveCount(0)
        ->and($report['officePayment'])->toHaveCount(0);
});

