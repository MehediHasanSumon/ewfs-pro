<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SalesReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    Schema::create('shifts', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->time('start_time')->nullable();
        $table->time('end_time')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('code')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id')->nullable();
        $table->foreignId('unit_id')->nullable();
        $table->string('product_name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('mobile', 50)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vehicles', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id')->nullable();
        $table->string('vehicle_number');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 50)->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('group_id')->nullable();
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->string('entry_no', 50);
        $table->string('status', 20)->default('posted');
        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_id')->nullable();
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('vehicle_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('sale_type', 30)->default('regular');
        $table->date('sale_date');
        $table->string('invoice_no', 50)->nullable();
        $table->string('memo_no', 50)->nullable();
        $table->string('customer_name_snapshot', 150)->nullable();
        $table->string('vehicle_number_snapshot', 50)->nullable();
        $table->decimal('subtotal', 24, 4)->default(0);
        $table->decimal('discount_total', 24, 4)->default(0);
        $table->decimal('tax_total', 24, 4)->default(0);
        $table->decimal('grand_total', 24, 4)->default(0);
        $table->string('status', 20)->default('paid');
        $table->foreignId('created_by')->nullable();
        $table->foreignId('posted_by')->nullable();
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id');
        $table->integer('line_no')->default(1);
        $table->foreignId('product_id')->nullable();
        $table->string('product_name_snapshot', 150)->nullable();
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('discount_amount', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('sale_id');
        $table->foreignId('account_id')->nullable();
        $table->string('payment_method', 50)->default('cash');
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('shift_id')->nullable();
        $table->date('sale_date');
        $table->string('invoice_no', 50)->nullable();
        $table->string('memo_no', 50)->nullable();
        $table->decimal('grand_total', 24, 4)->default(0);
        $table->string('status', 20)->default('posted');
        $table->foreignId('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_id');
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('customer_name_snapshot', 150)->nullable();
        $table->decimal('subtotal', 24, 4)->default(0);
        $table->decimal('grand_total', 24, 4)->default(0);
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->integer('line_no')->default(1);
        $table->foreignId('vehicle_id')->nullable();
        $table->foreignId('product_id')->nullable();
        $table->string('product_name_snapshot', 150)->nullable();
        $table->string('unit_name_snapshot', 50)->nullable();
        $table->string('vehicle_number_snapshot', 50)->nullable();
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('discount_amount', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4);
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('credit_sale_items');
    Schema::dropIfExists('credit_sale_customers');
    Schema::dropIfExists('credit_sales');
    Schema::dropIfExists('sale_payment_details');
    Schema::dropIfExists('sale_items');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('journal_entries');
    Schema::dropIfExists('accounts');
    Schema::dropIfExists('groups');
    Schema::dropIfExists('vehicles');
    Schema::dropIfExists('customers');
    Schema::dropIfExists('products');
    Schema::dropIfExists('units');
    Schema::dropIfExists('categories');
    Schema::dropIfExists('shifts');
    Schema::dropIfExists('users');
});

it('generates customer-wise grouped sales report matching test requirements', function (): void {
    $shift = Shift::query()->create(['name' => 'Morning Shift']);
    $userId = DB::table('users')->insertGetId([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $category = DB::table('categories')->insertGetId(['name' => 'Fuel', 'code' => '1001']);
    $unit = DB::table('units')->insertGetId(['name' => 'Litre']);
    $diesel = DB::table('products')->insertGetId(['product_name' => 'Diesel', 'category_id' => $category, 'unit_id' => $unit]);
    $octane = DB::table('products')->insertGetId(['product_name' => 'Octane', 'category_id' => $category, 'unit_id' => $unit]);

    $custA = Customer::query()->create(['name' => 'Customer A']);
    $custB = Customer::query()->create(['name' => 'Customer B']);

    $vehA = Vehicle::query()->create(['customer_id' => $custA->id, 'vehicle_number' => 'DHK-METRO-11-2233']);
    $vehB = Vehicle::query()->create(['customer_id' => $custB->id, 'vehicle_number' => 'CTG-METRO-44-5566']);

    $cashGroup = DB::table('groups')->insertGetId(['name' => 'Cash in hand', 'code' => '100020002']);
    $cashAccount = DB::table('accounts')->insertGetId(['name' => 'Cash Account', 'group_id' => $cashGroup]);

    $bankGroup = DB::table('groups')->insertGetId(['name' => 'Bank Account', 'code' => '100020004']);
    $bankAccount = DB::table('accounts')->insertGetId(['name' => 'DBBL Bank', 'group_id' => $bankGroup]);

    $mobileGroup = DB::table('groups')->insertGetId(['name' => 'Mobile Bank', 'code' => '100020003']);
    $bkashAccount = DB::table('accounts')->insertGetId(['name' => 'bKash Account', 'group_id' => $mobileGroup]);

    // Customer A:
    // Sale 1: Cash 1,000 (Diesel 10L @ 100)
    $j1 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-01', 'status' => 'posted']);
    $s1 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'customer_id' => $custA->id,
        'vehicle_id' => $vehA->id,
        'journal_entry_id' => $j1,
        'sale_date' => '2026-08-01',
        'invoice_no' => 'INV-001',
        'memo_no' => 'MEMO-001',
        'customer_name_snapshot' => 'Customer A',
        'vehicle_number_snapshot' => 'DHK-METRO-11-2233',
        'grand_total' => 1000,
        'status' => 'paid',
        'created_by' => $userId,
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $s1, 'account_id' => $cashAccount, 'payment_method' => 'cash']);
    DB::table('sale_items')->insert(['sale_id' => $s1, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 10, 'unit_price' => 100, 'line_total' => 1000]);

    // Sale 2: Credit 2,000 (Diesel 20L @ 100)
    $j2 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-02', 'status' => 'posted']);
    $cs2 = DB::table('credit_sales')->insertGetId([
        'shift_id' => $shift->id,
        'sale_date' => '2026-08-02',
        'invoice_no' => 'INV-002',
        'memo_no' => 'MEMO-002',
        'grand_total' => 2000,
        'status' => 'posted',
        'created_by' => $userId,
    ]);
    $csc2 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $cs2,
        'customer_id' => $custA->id,
        'journal_entry_id' => $j2,
        'customer_name_snapshot' => 'Customer A',
        'grand_total' => 2000,
    ]);
    DB::table('credit_sale_items')->insert(['credit_sale_customer_id' => $csc2, 'vehicle_id' => $vehA->id, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'vehicle_number_snapshot' => 'DHK-METRO-11-2233', 'quantity' => 20, 'unit_price' => 100, 'line_total' => 2000]);

    // Sale 3: Bank 3,000 (Octane 25L @ 120)
    $j3 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-03', 'status' => 'posted']);
    $s3 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'customer_id' => $custA->id,
        'vehicle_id' => $vehA->id,
        'journal_entry_id' => $j3,
        'sale_date' => '2026-08-03',
        'invoice_no' => 'INV-003',
        'memo_no' => 'MEMO-003',
        'customer_name_snapshot' => 'Customer A',
        'vehicle_number_snapshot' => 'DHK-METRO-11-2233',
        'grand_total' => 3000,
        'status' => 'paid',
        'created_by' => $userId,
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $s3, 'account_id' => $bankAccount, 'payment_method' => 'bank']);
    DB::table('sale_items')->insert(['sale_id' => $s3, 'product_id' => $octane, 'product_name_snapshot' => 'Octane', 'unit_name_snapshot' => 'Litre', 'quantity' => 25, 'unit_price' => 120, 'line_total' => 3000]);

    // Sale 4: Mobile Bank 4,000 (Diesel 40L @ 100)
    $j4 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-04', 'status' => 'posted']);
    $s4 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'customer_id' => $custA->id,
        'vehicle_id' => $vehA->id,
        'journal_entry_id' => $j4,
        'sale_date' => '2026-08-04',
        'invoice_no' => 'INV-004',
        'memo_no' => 'MEMO-004',
        'customer_name_snapshot' => 'Customer A',
        'vehicle_number_snapshot' => 'DHK-METRO-11-2233',
        'grand_total' => 4000,
        'status' => 'paid',
        'created_by' => $userId,
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $s4, 'account_id' => $bkashAccount, 'payment_method' => 'mobile_bank']);
    DB::table('sale_items')->insert(['sale_id' => $s4, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 40, 'unit_price' => 100, 'line_total' => 4000]);

    // Customer B:
    // Sale 5: Cash 5,000 (Diesel 50L @ 100)
    $j5 = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-05', 'status' => 'posted']);
    $s5 = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'customer_id' => $custB->id,
        'vehicle_id' => $vehB->id,
        'journal_entry_id' => $j5,
        'sale_date' => '2026-08-05',
        'invoice_no' => 'INV-005',
        'memo_no' => 'MEMO-005',
        'customer_name_snapshot' => 'Customer B',
        'vehicle_number_snapshot' => 'CTG-METRO-44-5566',
        'grand_total' => 5000,
        'status' => 'paid',
        'created_by' => $userId,
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $s5, 'account_id' => $cashAccount, 'payment_method' => 'cash']);
    DB::table('sale_items')->insert(['sale_id' => $s5, 'product_id' => $diesel, 'product_name_snapshot' => 'Diesel', 'unit_name_snapshot' => 'Litre', 'quantity' => 50, 'unit_price' => 100, 'line_total' => 5000]);

    $service = app(SalesReportService::class);
    $report = $service->report([
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-05',
    ]);

    // Check customer grouping
    expect($report['total_customers'])->toBe(2)
        ->and($report['customers'])->toHaveCount(2);

    $customerAData = collect($report['customers'])->firstWhere('customer_name', 'Customer A');
    $customerBData = collect($report['customers'])->firstWhere('customer_name', 'Customer B');

    // Customer A checks:
    // Total from Customer A = 10,000 (1,000 Cash + 2,000 Credit + 3,000 Bank + 4,000 Mobile Bank)
    expect($customerAData['total_amount'])->toBe(10000.00)
        ->and($customerAData['total_quantity'])->toBe(95.0)
        ->and($customerAData['sales'])->toHaveCount(4);

    $typesInA = collect($customerAData['sales'])->pluck('type')->all();
    expect($typesInA)->toContain('Cash')
        ->and($typesInA)->toContain('Credit')
        ->and($typesInA)->toContain('Bank')
        ->and($typesInA)->toContain('Mobile Bank');

    // Customer B checks:
    // Total from Customer B = 5,000
    expect($customerBData['total_amount'])->toBe(5000.00)
        ->and($customerBData['total_quantity'])->toBe(50.0)
        ->and($customerBData['sales'])->toHaveCount(1);

    // Grand Total checks:
    // Grand Total Sales = 15,000
    // Grand Total Quantity = 145
    expect($report['grand_total_amount'])->toBe(15000.00)
        ->and($report['grand_total_quantity'])->toBe(145.0)
        ->and($report['total_invoices'])->toBe(5);

    // Test Customer Filter
    $custBOnlyReport = $service->report([
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-05',
        'customer_id' => $custB->id,
    ]);
    expect($custBOnlyReport['grand_total_amount'])->toBe(5000.00)
        ->and($custBOnlyReport['total_customers'])->toBe(1);

    // Test Date Range Filter
    $dateFilterReport = $service->report([
        'start_date' => '2026-08-05',
        'end_date' => '2026-08-05',
    ]);
    expect($dateFilterReport['grand_total_amount'])->toBe(5000.00)
        ->and($dateFilterReport['total_customers'])->toBe(1);
});

it('renders every product item on a multi-item invoice as a separate row and handles walk-in customer', function (): void {
    $shift = Shift::query()->create(['name' => 'Day Shift']);
    $userId = DB::table('users')->insertGetId([
        'name' => 'Cashier',
        'email' => 'cashier@example.com',
        'password' => 'secret',
    ]);

    $category = DB::table('categories')->insertGetId(['name' => 'Fuel', 'code' => '1001']);
    $unit = DB::table('units')->insertGetId(['name' => 'Litre']);
    $diesel = DB::table('products')->insertGetId(['product_name' => 'Diesel', 'category_id' => $category, 'unit_id' => $unit]);
    $engineOil = DB::table('products')->insertGetId(['product_name' => 'Engine Oil', 'category_id' => $category, 'unit_id' => $unit]);
    $brakeOil = DB::table('products')->insertGetId(['product_name' => 'Brake Oil', 'category_id' => $category, 'unit_id' => $unit]);

    $cashGroup = DB::table('groups')->insertGetId(['name' => 'Cash in hand', 'code' => '100020002']);
    $cashAccount = DB::table('accounts')->insertGetId(['name' => 'Cash Account', 'group_id' => $cashGroup]);

    // Walk-in non-permanent customer with multi-item invoice:
    // Diesel: 50L @ 115 = 5,750
    // Engine Oil: 2L @ 500 = 1,000
    // Brake Oil: 1L @ 300 = 300
    // Invoice Total = 7,050
    $jMulti = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-MULTI', 'status' => 'posted']);
    $sMulti = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'customer_id' => null, // non-permanent
        'vehicle_id' => null,
        'journal_entry_id' => $jMulti,
        'sale_date' => '2026-08-10',
        'invoice_no' => 'INV-000001',
        'memo_no' => 'MEMO-999',
        'customer_name_snapshot' => 'Mr. Walkin Traveler',
        'vehicle_number_snapshot' => 'DHK-METRO-KA-1111',
        'grand_total' => 7050,
        'status' => 'paid',
        'created_by' => $userId,
    ]);
    DB::table('sale_payment_details')->insert(['sale_id' => $sMulti, 'account_id' => $cashAccount, 'payment_method' => 'cash']);

    DB::table('sale_items')->insert([
        [
            'sale_id' => $sMulti,
            'line_no' => 1,
            'product_id' => $diesel,
            'product_name_snapshot' => 'Diesel',
            'unit_name_snapshot' => 'Litre',
            'quantity' => 50,
            'unit_price' => 115,
            'discount_amount' => 0,
            'line_total' => 5750,
        ],
        [
            'sale_id' => $sMulti,
            'line_no' => 2,
            'product_id' => $engineOil,
            'product_name_snapshot' => 'Engine Oil',
            'unit_name_snapshot' => 'Litre',
            'quantity' => 2,
            'unit_price' => 500,
            'discount_amount' => 0,
            'line_total' => 1000,
        ],
        [
            'sale_id' => $sMulti,
            'line_no' => 3,
            'product_id' => $brakeOil,
            'product_name_snapshot' => 'Brake Oil',
            'unit_name_snapshot' => 'Litre',
            'quantity' => 1,
            'unit_price' => 300,
            'discount_amount' => 0,
            'line_total' => 300,
        ],
    ]);

    // Unposted / draft sale (should be EXCLUDED)
    $jDraft = DB::table('journal_entries')->insertGetId(['entry_no' => 'JRN-DRAFT', 'status' => 'draft']);
    $sDraft = DB::table('sales')->insertGetId([
        'shift_id' => $shift->id,
        'journal_entry_id' => $jDraft,
        'sale_date' => '2026-08-10',
        'invoice_no' => 'INV-DRAFT',
        'customer_name_snapshot' => 'Mr. Draft',
        'grand_total' => 9999,
        'status' => 'draft',
        'created_by' => $userId,
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $sDraft,
        'line_no' => 1,
        'product_id' => $diesel,
        'product_name_snapshot' => 'Diesel',
        'quantity' => 100,
        'unit_price' => 100,
        'line_total' => 10000,
    ]);

    $service = app(SalesReportService::class);
    $report = $service->report([
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-10',
    ]);

    expect($report['total_customers'])->toBe(1)
        ->and($report['customers'])->toHaveCount(1);

    $walkinGroup = $report['customers'][0];
    expect($walkinGroup['customer_name'])->toBe('Mr. Walkin Traveler')
        ->and($walkinGroup['sales'])->toHaveCount(3) // 3 separate rows
        ->and($walkinGroup['total_quantity'])->toBe(53.0)
        ->and($walkinGroup['total_amount'])->toBe(7050.00);

    // Verify individual items
    $productNames = collect($walkinGroup['sales'])->pluck('product_name')->all();
    expect($productNames)->toContain('Diesel')
        ->and($productNames)->toContain('Engine Oil')
        ->and($productNames)->toContain('Brake Oil');

    // Verify grand totals
    expect($report['grand_total_amount'])->toBe(7050.00)
        ->and($report['grand_total_quantity'])->toBe(53.0)
        ->and($report['total_invoices'])->toBe(1);
});
