<?php

use App\Models\Sale;
use App\Services\AccountingService;
use App\Services\DispenserCalculationService;
use App\Services\InventoryService;
use App\Services\ShiftClosingService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
    });

    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->string('status');
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('shift_id');
        $table->unsignedBigInteger('journal_entry_id');
        $table->date('sale_date');
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('sale_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('category_id');
        $table->decimal('quantity', 24, 6);
        $table->decimal('line_total', 24, 4);
    });

    Schema::create('credit_sales', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('shift_id');
        $table->date('sale_date');
    });

    Schema::create('credit_sale_customers', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('credit_sale_id');
        $table->unsignedBigInteger('journal_entry_id');
    });

    Schema::create('credit_sale_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('credit_sale_customer_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('category_id');
        $table->decimal('quantity', 24, 6);
        $table->decimal('line_total', 24, 4);
    });

    Schema::create('inventory_movements', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->string('source_type');
        $table->unsignedBigInteger('reversal_of_id')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('inventory_movements');
    Schema::dropIfExists('credit_sale_items');
    Schema::dropIfExists('credit_sale_customers');
    Schema::dropIfExists('credit_sales');
    Schema::dropIfExists('sale_items');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('journal_entries');
    Schema::dropIfExists('categories');
});

it('separates new immediate fuel movements from legacy deferred fuel sales', function () {
    DB::table('categories')->insert([
        'id' => 1,
        'code' => config('erp.product_categories.oil'),
    ]);
    DB::table('journal_entries')->insert([
        ['id' => 1, 'status' => 'posted'],
        ['id' => 2, 'status' => 'posted'],
    ]);
    DB::table('sales')->insert([
        'id' => 1,
        'shift_id' => 1,
        'journal_entry_id' => 1,
        'sale_date' => '2026-08-03',
    ]);
    DB::table('sale_items')->insert([
        'id' => 10,
        'sale_id' => 1,
        'product_id' => 100,
        'category_id' => 1,
        'quantity' => 4,
        'line_total' => 400,
    ]);
    DB::table('inventory_movements')->insert([
        'source_line_id' => 10,
        'source_type' => Sale::class,
        'reversal_of_id' => null,
    ]);
    DB::table('credit_sales')->insert([
        'id' => 2,
        'shift_id' => 1,
        'sale_date' => '2026-08-03',
    ]);
    DB::table('credit_sale_customers')->insert([
        'id' => 20,
        'credit_sale_id' => 2,
        'journal_entry_id' => 2,
    ]);
    DB::table('credit_sale_items')->insert([
        'id' => 30,
        'credit_sale_customer_id' => 20,
        'product_id' => 100,
        'category_id' => 1,
        'quantity' => 3,
        'line_total' => 300,
    ]);

    $service = new ShiftClosingService(
        Mockery::mock(AccountingService::class),
        Mockery::mock(InventoryService::class),
        Mockery::mock(SystemAccountService::class),
        Mockery::mock(DispenserCalculationService::class)
    );
    $method = new ReflectionMethod($service, 'recordedFuelSales');
    $recorded = $method->invoke($service, '2026-08-03', 1);

    expect((float) $recorded[100]['quantity'])->toBe(7.0)
        ->and((float) $recorded[100]['inventory_quantity'])->toBe(4.0)
        ->and((float) $recorded[100]['amount'])->toBe(700.0);
});
