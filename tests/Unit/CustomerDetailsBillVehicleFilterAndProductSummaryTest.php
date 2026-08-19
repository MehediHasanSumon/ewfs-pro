<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('company_settings', function (Blueprint $table) {
        $table->id();
        $table->string('company_name');
        $table->string('company_address')->nullable();
        $table->string('company_mobile')->nullable();
        $table->string('company_phone')->nullable();
        $table->string('fax')->nullable();
        $table->string('company_email')->nullable();
        $table->string('currency', 3)->default('BDT');
        $table->string('company_logo')->nullable();
        $table->string('pdf_watermark_image')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('mobile')->nullable();
        $table->text('address')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vehicles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id');
        $table->string('vehicle_number');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->string('entry_no');
        $table->date('business_date');
        $table->string('status')->default('posted');
        $table->timestamps();
    });

    Schema::create('credit_sales', function (Blueprint $table) {
        $table->id();
        $table->string('invoice_no');
        $table->string('memo_no')->nullable();
        $table->date('sale_date');
        $table->timestamps();
    });

    Schema::create('credit_sale_customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_id');
        $table->foreignId('customer_id');
        $table->foreignId('journal_entry_id');
        $table->timestamps();
    });

    Schema::create('credit_sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_sale_customer_id');
        $table->unsignedInteger('line_no')->default(1);
        $table->foreignId('vehicle_id')->nullable();
        $table->foreignId('product_id')->nullable();
        $table->string('vehicle_number_snapshot')->nullable();
        $table->string('product_name_snapshot')->nullable();
        $table->string('unit_name_snapshot')->nullable();
        $table->decimal('unit_price', 15, 2)->default(0);
        $table->decimal('quantity', 15, 2)->default(0);
        $table->decimal('line_total', 15, 2)->default(0);
        $table->timestamps();
    });
});

test('Customer Details Bill aggregates product-wise summary and filters by vehicle correctly', function () {
    $customer = Customer::create([
        'name' => 'ABC Company',
        'mobile' => '01700000000',
        'address' => 'Dhaka, Bangladesh',
    ]);

    $vehicleA = Vehicle::create([
        'customer_id' => $customer->id,
        'vehicle_number' => 'Dhaka Metro-GA-1234',
    ]);

    $vehicleB = Vehicle::create([
        'customer_id' => $customer->id,
        'vehicle_number' => 'Dhaka Metro-GA-5678',
    ]);

    $journalEntry = DB::table('journal_entries')->insertGetId([
        'entry_no' => 'JE-001',
        'business_date' => '2026-08-10',
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $creditSale = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'INV-1001',
        'memo_no' => 'MEMO-501',
        'sale_date' => '2026-08-10',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $creditSaleCustomer = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $creditSale,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Vehicle A items:
    // Diesel 50 Litre = 5,750 (price 115)
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 1,
        'vehicle_id' => $vehicleA->id,
        'product_id' => 1,
        'vehicle_number_snapshot' => 'Dhaka Metro-GA-1234',
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 50.00,
        'line_total' => 5750.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Diesel 30 Litre = 3,450 (price 115)
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 2,
        'vehicle_id' => $vehicleA->id,
        'product_id' => 1,
        'vehicle_number_snapshot' => 'Dhaka Metro-GA-1234',
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 30.00,
        'line_total' => 3450.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Engine Oil 5 Litre = 2,500 (price 500)
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 3,
        'vehicle_id' => $vehicleA->id,
        'product_id' => 2,
        'vehicle_number_snapshot' => 'Dhaka Metro-GA-1234',
        'product_name_snapshot' => 'Engine Oil',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 500.00,
        'quantity' => 5.00,
        'line_total' => 2500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Vehicle B item:
    // Diesel 100 Litre = 11,500 (price 115)
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 4,
        'vehicle_id' => $vehicleB->id,
        'product_id' => 1,
        'vehicle_number_snapshot' => 'Dhaka Metro-GA-5678',
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 100.00,
        'line_total' => 11500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(CustomerReportService::class);

    // 1. All vehicles (vehicle filter = null)
    $allBills = $service->detailBills('2026-08-01', '2026-08-20', $customer->id);
    expect($allBills)->toHaveCount(1);
    $bill = $allBills[0];
    expect($bill['total_quantity'])->toBe(185.0);
    expect($bill['total_amount'])->toBe(23200.0);
    expect($bill['vehicle_groups'])->toHaveCount(2);

    // Product summary check for all vehicles
    $summary = collect($bill['product_summary']);
    expect($summary)->toHaveCount(2);

    $dieselSummary = $summary->firstWhere('product_name', 'Diesel');
    expect($dieselSummary['quantity'])->toBe(180.0);
    expect($dieselSummary['total_amount'])->toBe(20700.0);
    expect($dieselSummary['price'])->toBe(115.0);

    $oilSummary = $summary->firstWhere('product_name', 'Engine Oil');
    expect($oilSummary['quantity'])->toBe(5.0);
    expect($oilSummary['total_amount'])->toBe(2500.0);
    expect($oilSummary['price'])->toBe(500.0);

    // 2. Filter by Vehicle A only
    $vehicleABills = $service->detailBills('2026-08-01', '2026-08-20', $customer->id, $vehicleA->id);
    expect($vehicleABills)->toHaveCount(1);
    $vABill = $vehicleABills[0];

    expect($vABill['vehicle_groups'])->toHaveCount(1);
    expect($vABill['vehicle_groups'][0]['vehicle_number'])->toBe('Dhaka Metro-GA-1234');
    expect($vABill['total_quantity'])->toBe(85.0);
    expect($vABill['total_amount'])->toBe(11700.0);

    $vASummary = collect($vABill['product_summary']);
    expect($vASummary)->toHaveCount(2);

    $vADiesel = $vASummary->firstWhere('product_name', 'Diesel');
    expect($vADiesel['quantity'])->toBe(80.0);
    expect($vADiesel['total_amount'])->toBe(9200.0);
    expect($vADiesel['price'])->toBe(115.0);

    $vAOil = $vASummary->firstWhere('product_name', 'Engine Oil');
    expect($vAOil['quantity'])->toBe(5.0);
    expect($vAOil['total_amount'])->toBe(2500.0);
    expect($vAOil['price'])->toBe(500.0);

    $productSummaryTotal = $vASummary->sum('total_amount');
    expect($productSummaryTotal)->toBe(11700.0);

    // 3. Test PDF Rendering
    $companySetting = CompanySetting::create([
        'company_name' => 'East West Filling Station',
        'currency' => 'BDT',
        'status' => true,
    ]);

    $pdf = Pdf::loadView('pdf.customer-details-bill', [
        'bills' => $vehicleABills,
        'companySetting' => $companySetting,
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-20',
        'selectedVehicle' => $vehicleA,
    ]);
    $output = $pdf->output();
    expect(strlen($output))->toBeGreaterThan(1000);
});

test('Customer Details Bill separates same product sold at different rates into distinct summary rows', function () {
    $customer = Customer::create([
        'name' => 'XYZ Logistics',
        'mobile' => '01800000000',
    ]);

    $journalEntry = DB::table('journal_entries')->insertGetId([
        'entry_no' => 'JE-002',
        'business_date' => '2026-08-15',
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $creditSale = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'INV-2001',
        'sale_date' => '2026-08-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $creditSaleCustomer = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $creditSale,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Sale 1: Diesel Rate = 115, Quantity = 50, Total = 5,750
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 1,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 50.00,
        'line_total' => 5750.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Sale 2: Diesel Rate = 115, Quantity = 20, Total = 2,300
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 2,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 20.00,
        'line_total' => 2300.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Sale 3: Diesel Rate = 120, Quantity = 30, Total = 3,600
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $creditSaleCustomer,
        'line_no' => 3,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 120.00,
        'quantity' => 30.00,
        'line_total' => 3600.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(CustomerReportService::class);
    $bills = $service->detailBills('2026-08-01', '2026-08-20', $customer->id);

    expect($bills)->toHaveCount(1);
    $summary = $bills[0]['product_summary'];

    // Must have 2 distinct summary rows for Diesel (one at 115 and one at 120)
    expect($summary)->toHaveCount(2);

    $diesel115 = collect($summary)->firstWhere('price', 115.0);
    expect($diesel115)->not->toBeNull();
    expect($diesel115['product_name'])->toBe('Diesel');
    expect($diesel115['unit_name'])->toBe('Litre');
    expect($diesel115['quantity'])->toBe(70.0);
    expect($diesel115['total_amount'])->toBe(8050.0);

    $diesel120 = collect($summary)->firstWhere('price', 120.0);
    expect($diesel120)->not->toBeNull();
    expect($diesel120['product_name'])->toBe('Diesel');
    expect($diesel120['unit_name'])->toBe('Litre');
    expect($diesel120['quantity'])->toBe(30.0);
    expect($diesel120['total_amount'])->toBe(3600.0);

    expect(collect($summary)->sum('total_amount'))->toBe(11650.0);
    expect($bills[0]['total_amount'])->toBe(11650.0);
});
