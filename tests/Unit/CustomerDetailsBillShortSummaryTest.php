<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerDetailsShortSummaryService;
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

    Schema::create('product_rates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->decimal('purchase_price', 15, 2)->nullable();
        $table->decimal('sales_price', 15, 2)->nullable();
        $table->date('effective_date');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
});

test('Customer Details Bill Short Summary groups by product and rate according to test case 26', function () {
    $customer = Customer::create([
        'name' => 'ACI Logistics Ltd.',
        'mobile' => '017XXXXXXXX',
        'address' => '270 Novo Tower, Level-8, Tejgaon Industrial Area, Dhaka-1208, Bangladesh.',
    ]);

    $journalEntry = DB::table('journal_entries')->insertGetId([
        'entry_no' => 'JE-SHORT-1',
        'business_date' => '2026-08-01',
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Diesel @ 115, Qty 1000, Amount 115,000
    $sale1 = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'IN-001',
        'sale_date' => '2026-08-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csCust1 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $sale1,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $csCust1,
        'line_no' => 1,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 1000.00,
        'line_total' => 115000.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Diesel @ 115, Qty 709.65, Amount 81,610
    $sale2 = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'IN-002',
        'sale_date' => '2026-08-05',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csCust2 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $sale2,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $csCust2,
        'line_no' => 1,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 709.65,
        'line_total' => 81610.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Diesel @ 120, Qty 100, Amount 12,000
    $sale3 = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'IN-003',
        'sale_date' => '2026-08-10',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csCust3 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $sale3,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $csCust3,
        'line_no' => 1,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 120.00,
        'quantity' => 100.00,
        'line_total' => 12000.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(CustomerDetailsShortSummaryService::class);
    $report = $service->getShortSummary('2026-08-01', '2026-08-18', $customer->id);

    expect($report)->not->toBeNull();
    expect($report['customer']['name'])->toBe('ACI Logistics Ltd.');
    expect($report['period']['formatted'])->toBe('2026-08-01 to 2026-08-18');

    // Main table has 2 rows
    $summary = $report['product_summary'];
    expect($summary)->toHaveCount(2);

    // Row 1: Diesel @ 115.00, Qty 1,709.65, Amount 196,610.00
    expect($summary[0]['sn'])->toBe(1);
    expect($summary[0]['product_name'])->toBe('Diesel');
    expect($summary[0]['price'])->toBe(115.00);
    expect($summary[0]['quantity'])->toBe(1709.65);
    expect($summary[0]['total_amount'])->toBe(196610.00);

    // Row 2: Diesel @ 120.00, Qty 100.00, Amount 12,000.00
    expect($summary[1]['sn'])->toBe(2);
    expect($summary[1]['product_name'])->toBe('Diesel');
    expect($summary[1]['price'])->toBe(120.00);
    expect($summary[1]['quantity'])->toBe(100.00);
    expect($summary[1]['total_amount'])->toBe(12000.00);

    // Total Slip Quantity = 3 distinct sales
    expect($report['total_slip_quantity'])->toBe(3);

    // Total = 208,610.00
    expect($report['total'])->toBe(208610.00);
    expect($report['total_quantity'])->toBe(1809.65);
    expect($report['grand_total'])->toBe(208610.00);
    expect($report['amount_in_words'])->toContain('Taka Only');

    // PDF generation check
    $companySetting = CompanySetting::create([
        'company_name' => 'East West Filling Station',
        'currency' => 'BDT',
        'status' => true,
    ]);

    $pdf = Pdf::loadView('pdf.customer-details-bill-short-summary', [
        'report' => $report,
        'companySetting' => $companySetting,
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-18',
    ]);
    expect(strlen($pdf->output()))->toBeGreaterThan(1000);
});

test('Customer Details Bill Short Summary filters by vehicle when supplied', function () {
    $customer = Customer::create([
        'name' => 'ACI Logistics Ltd.',
    ]);

    $vehicleA = Vehicle::create([
        'customer_id' => $customer->id,
        'vehicle_number' => 'DM-1122',
    ]);

    $vehicleB = Vehicle::create([
        'customer_id' => $customer->id,
        'vehicle_number' => 'DM-3344',
    ]);

    $journalEntry = DB::table('journal_entries')->insertGetId([
        'entry_no' => 'JE-SHORT-2',
        'business_date' => '2026-08-01',
        'status' => 'posted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Vehicle A sale
    $sale1 = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'IN-VA-1',
        'sale_date' => '2026-08-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csCust1 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $sale1,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $csCust1,
        'vehicle_id' => $vehicleA->id,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 500.00,
        'line_total' => 57500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Vehicle B sale
    $sale2 = DB::table('credit_sales')->insertGetId([
        'invoice_no' => 'IN-VB-1',
        'sale_date' => '2026-08-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csCust2 = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $sale2,
        'customer_id' => $customer->id,
        'journal_entry_id' => $journalEntry,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $csCust2,
        'vehicle_id' => $vehicleB->id,
        'product_id' => 1,
        'product_name_snapshot' => 'Diesel',
        'unit_name_snapshot' => 'Litre',
        'unit_price' => 115.00,
        'quantity' => 300.00,
        'line_total' => 34500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(CustomerDetailsShortSummaryService::class);

    // Filtering by Vehicle A
    $reportA = $service->getShortSummary('2026-08-01', '2026-08-01', $customer->id, $vehicleA->id);
    expect($reportA['selected_vehicle']['vehicle_number'])->toBe('DM-1122');
    expect($reportA['product_summary'])->toHaveCount(1);
    expect($reportA['total_quantity'])->toBe(500.00);
    expect($reportA['total'])->toBe(57500.00);
    expect($reportA['total_slip_quantity'])->toBe(1);

    // Without vehicle filter (all vehicles)
    $reportAll = $service->getShortSummary('2026-08-01', '2026-08-01', $customer->id);
    expect($reportAll['selected_vehicle'])->toBeNull();
    expect($reportAll['product_summary'])->toHaveCount(1);
    expect($reportAll['total_quantity'])->toBe(800.00);
    expect($reportAll['total'])->toBe(92000.00);
    expect($reportAll['total_slip_quantity'])->toBe(2);
});
