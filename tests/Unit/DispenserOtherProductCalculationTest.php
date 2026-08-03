<?php

use App\Helpers\ErpHelper;
use App\Models\Product;
use App\Services\DispenserCalculationService;
use App\Services\InventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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

    Schema::create('product_rates', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id');
        $table->decimal('purchase_price', 24, 6)->nullable();
        $table->decimal('sales_price', 24, 6)->nullable();
        $table->date('effective_date');
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
        $table->unsignedBigInteger('last_movement_id')->nullable();
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamp('refreshed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('inventory_movements', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('product_id');
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->unsignedBigInteger('journal_entry_id')->nullable();
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('movement_type', 64);
        $table->decimal('quantity_in', 24, 6)->default(0);
        $table->decimal('quantity_out', 24, 6)->default(0);
        $table->decimal('before_stock', 24, 6)->nullable();
        $table->decimal('after_stock', 24, 6)->nullable();
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('total_cost', 24, 4)->default(0);
        $table->string('source_type', 100);
        $table->unsignedBigInteger('source_id')->nullable();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->text('remarks')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key', 150)->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->string('status', 20);
    });

    Schema::create('journal_lines', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('journal_entry_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->string('payment_method', 30)->nullable();
    });

    Schema::create('sales', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('shift_id');
        $table->unsignedBigInteger('journal_entry_id');
        $table->string('sale_type', 20);
        $table->date('sale_date');
    });

    Schema::create('sale_payment_details', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('sale_id');
        $table->string('payment_method', 30);
    });

    Schema::create('sale_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('sale_id');
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('category_id');
        $table->unsignedBigInteger('unit_id');
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
        $table->unsignedBigInteger('unit_id');
        $table->decimal('quantity', 24, 6);
        $table->decimal('line_total', 24, 4);
    });
});

afterEach(function (): void {
    Schema::dropIfExists('credit_sale_items');
    Schema::dropIfExists('credit_sale_customers');
    Schema::dropIfExists('credit_sales');
    Schema::dropIfExists('sale_items');
    Schema::dropIfExists('sale_payment_details');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('journal_lines');
    Schema::dropIfExists('journal_entries');
    Schema::dropIfExists('inventory_movements');
    Schema::dropIfExists('stocks');
    Schema::dropIfExists('product_rates');
    Schema::dropIfExists('products');
    Schema::dropIfExists('units');
    Schema::dropIfExists('categories');
});

function createOtherProductFixture(): array
{
    $categoryIds = collect([
        'oil' => ErpHelper::oilCategoryCode(),
        'gas' => ErpHelper::gasCategoryCode(),
        'lubricant' => ErpHelper::lubricantCategoryCode(),
        'future' => '1099',
    ])->mapWithKeys(fn (string $code, string $key) => [
        $key => DB::table('categories')->insertGetId([
            'name' => ucfirst($key),
            'code' => $code,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    ]);
    $pieceUnitId = DB::table('units')->insertGetId([
        'name' => 'Piece',
        'quantity_scale' => 0,
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $litreUnitId = DB::table('units')->insertGetId([
        'name' => 'Litre',
        'quantity_scale' => 3,
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $products = collect([
        'oil' => [$categoryIds['oil'], $litreUnitId, 'Oil', 100, 80],
        'gas' => [$categoryIds['gas'], $litreUnitId, 'Gas', 120, 90],
        'lubricant' => [
            $categoryIds['lubricant'],
            $pieceUnitId,
            'Lubricant',
            25.50,
            10,
        ],
        'future' => [
            $categoryIds['future'],
            $litreUnitId,
            'Future Product',
            40,
            15,
        ],
    ])->mapWithKeys(function (array $data, string $key): array {
        [$categoryId, $unitId, $name, $price, $stock] = $data;
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'product_code' => strtoupper($key),
            'product_name' => $name,
            'is_inventory_item' => true,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_rates')->insert([
            'product_id' => $productId,
            'purchase_price' => $price / 2,
            'sales_price' => $price,
            'effective_date' => now()->toDateString(),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stocks')->insert([
            'product_id' => $productId,
            'current_stock' => $stock,
            'available_stock' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$key => Product::query()->findOrFail($productId)];
    });

    return compact('products');
}

function createPostedOtherProductSales(array $fixture): void
{
    $date = '2026-08-03';
    $shiftId = 1;
    $lubricant = $fixture['products']['lubricant'];
    $oil = $fixture['products']['oil'];

    $createSale = function (
        Product $product,
        string $saleType,
        float $quantity,
        float $amount,
        ?string $paymentMethod
    ) use ($date, $shiftId): void {
        $journalId = DB::table('journal_entries')->insertGetId([
            'status' => 'posted',
        ]);
        $saleId = DB::table('sales')->insertGetId([
            'shift_id' => $shiftId,
            'journal_entry_id' => $journalId,
            'sale_type' => $saleType,
            'sale_date' => $date,
        ]);

        if ($paymentMethod !== null) {
            DB::table('sale_payment_details')->insert([
                'sale_id' => $saleId,
                'payment_method' => $paymentMethod,
            ]);
        }

        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'quantity' => $quantity,
            'line_total' => $amount,
        ]);
    };

    $createSale($lubricant, 'regular', 2, 51, 'cash');
    $createSale($lubricant, 'regular', 1, 25.5, 'bank');
    $createSale($lubricant, 'white', 2, 40, null);
    $createSale($oil, 'regular', 5, 500, 'cash');

    $legacyJournalId = DB::table('journal_entries')->insertGetId([
        'status' => 'posted',
    ]);
    $legacySaleId = DB::table('sales')->insertGetId([
        'shift_id' => $shiftId,
        'journal_entry_id' => $legacyJournalId,
        'sale_type' => 'regular',
        'sale_date' => $date,
    ]);
    DB::table('journal_lines')->insert([
        'journal_entry_id' => $legacyJournalId,
        'debit_amount' => 10,
        'payment_method' => 'bank',
    ]);
    DB::table('sale_items')->insert([
        'sale_id' => $legacySaleId,
        'product_id' => $lubricant->id,
        'category_id' => $lubricant->category_id,
        'unit_id' => $lubricant->unit_id,
        'quantity' => 1,
        'line_total' => 10,
    ]);

    $creditJournalId = DB::table('journal_entries')->insertGetId([
        'status' => 'posted',
    ]);
    $creditSaleId = DB::table('credit_sales')->insertGetId([
        'shift_id' => $shiftId,
        'sale_date' => $date,
    ]);
    $allocationId = DB::table('credit_sale_customers')->insertGetId([
        'credit_sale_id' => $creditSaleId,
        'journal_entry_id' => $creditJournalId,
    ]);
    DB::table('credit_sale_items')->insert([
        'credit_sale_customer_id' => $allocationId,
        'product_id' => $lubricant->id,
        'category_id' => $lubricant->category_id,
        'unit_id' => $lubricant->unit_id,
        'quantity' => 3,
        'line_total' => 70,
    ]);
}

it('loads every active product outside configured Oil and Gas categories', function (): void {
    $fixture = createOtherProductFixture();
    $result = app(DispenserCalculationService::class)->calculate([]);

    expect(collect($result['products'])->pluck('id')->all())
        ->toContain(
            $fixture['products']['lubricant']->id,
            $fixture['products']['future']->id
        )
        ->not->toContain(
            $fixture['products']['oil']->id,
            $fixture['products']['gas']->id
        );
});

it('loads other products even when stock is zero or no stock row exists', function (): void {
    $fixture = createOtherProductFixture();
    $lubricant = $fixture['products']['lubricant'];
    $future = $fixture['products']['future'];

    DB::table('stocks')
        ->where('product_id', $lubricant->id)
        ->update([
            'current_stock' => 0,
            'available_stock' => 0,
        ]);
    DB::table('stocks')
        ->where('product_id', $future->id)
        ->delete();

    $products = collect(
        app(DispenserCalculationService::class)->calculate([])['products']
    )->keyBy('id');

    expect($products->keys()->all())
        ->toContain($lubricant->id, $future->id)
        ->and($products[$lubricant->id]['stock']['current_stock'])
        ->toBe(0.0)
        ->and($products[$future->id]['stock']['current_stock'])
        ->toBe(0.0);
});

it('auto-fills regular and credit quantities while keeping white sale manual', function (): void {
    $fixture = createOtherProductFixture();
    createPostedOtherProductSales($fixture);

    $result = app(DispenserCalculationService::class)
        ->calculateForShift('2026-08-03', 1);
    $row = collect($result['products'])->firstWhere(
        'id',
        $fixture['products']['lubricant']->id
    );

    expect($row['sell_quantity'])->toBe(7.0)
        ->and($row['auto_fill_quantity'])->toBe(7.0)
        ->and($row['recorded_quantity'])->toBe(9.0)
        ->and($row['quantity_variance'])->toBe(-2.0)
        ->and($row['regular_quantity'])->toBe(4.0)
        ->and($row['white_quantity'])->toBe(2.0)
        ->and($row['credit_quantity'])->toBe(3.0)
        ->and($row['remaining_stock'])->toBe(12.0)
        ->and($row['credit_sales'])->toBe(70.0)
        ->and($row['bank_sales'])->toBe(35.5)
        ->and($row['cash_sales'])->toBe(91.0)
        ->and($row['total_sales'])->toBe(196.5)
        ->and($result['summary']['total_sales'])->toBe(196.5)
        ->and($result['summary']['is_balanced'])->toBeTrue();
});

it('uses manual quantity only as a physical stock variance', function (): void {
    $fixture = createOtherProductFixture();
    createPostedOtherProductSales($fixture);
    $product = $fixture['products']['lubricant'];
    $calculations = app(DispenserCalculationService::class);

    $increase = collect($calculations->calculateForShift(
        '2026-08-03',
        1,
        [['product_id' => $product->id, 'quantity' => 11]]
    )['products'])->firstWhere('id', $product->id);
    $decrease = collect($calculations->calculateForShift(
        '2026-08-03',
        1,
        [['product_id' => $product->id, 'quantity' => 7]]
    )['products'])->firstWhere('id', $product->id);
    $zero = $calculations->resolveForShiftClosing(
        '2026-08-03',
        1,
        [[
            'product_id' => $product->id,
            'quantity' => 0,
            'employee_id' => null,
        ]]
    )->first();

    expect($increase['quantity_variance'])->toBe(2.0)
        ->and($increase['remaining_stock'])->toBe(8.0)
        ->and($increase['total_sales'])->toBe(196.5)
        ->and($decrease['quantity_variance'])->toBe(-2.0)
        ->and($decrease['remaining_stock'])->toBe(12.0)
        ->and($decrease['total_sales'])->toBe(196.5)
        ->and($zero['quantity'])->toBe(0.0)
        ->and($zero['quantity_variance'])->toBe(-9.0)
        ->and($zero['remaining_stock'])->toBe(19.0)
        ->and(fn () => $calculations->calculateForShift(
            '2026-08-03',
            1,
            [['product_id' => $product->id, 'quantity' => 20]]
        ))->toThrow(ValidationException::class)
        ->and(fn () => $calculations->resolveForShiftClosing(
            '2026-08-03',
            1,
            [[
                'product_id' => $product->id,
                'quantity' => 9,
                'recorded_quantity' => 8,
                'employee_id' => 99,
            ]]
        ))->toThrow(ValidationException::class);
});

it('calculates remaining stock, totals, and payment summary on the server', function (): void {
    $fixture = createOtherProductFixture();
    $product = $fixture['products']['lubricant'];
    $result = app(DispenserCalculationService::class)->calculate([
        ['product_id' => $product->id, 'quantity' => 3],
    ], 20, 10);
    $row = collect($result['products'])->firstWhere('id', $product->id);

    expect($row['remaining_stock'])->toBe(7.0)
        ->and($row['total_sales'])->toBe(76.5)
        ->and($result['summary']['total_sales'])->toBe(76.5)
        ->and($result['summary']['cash_sales'])->toBe(46.5)
        ->and($result['summary']['is_balanced'])->toBeTrue();
});

it('rejects dispenser categories, excess stock, and unsupported decimals', function (): void {
    $fixture = createOtherProductFixture();
    $calculations = app(DispenserCalculationService::class);

    expect(fn () => $calculations->calculate([
        ['product_id' => $fixture['products']['oil']->id, 'quantity' => 1],
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $calculations->calculate([
            [
                'product_id' => $fixture['products']['lubricant']->id,
                'quantity' => 11,
            ],
        ]))->toThrow(ValidationException::class)
        ->and(fn () => $calculations->calculate([
            [
                'product_id' => $fixture['products']['lubricant']->id,
                'quantity' => 1.5,
            ],
        ]))->toThrow(ValidationException::class);
});

it('uses authoritative prices and records the inventory movement', function (): void {
    $fixture = createOtherProductFixture();
    $product = $fixture['products']['lubricant'];
    $line = app(DispenserCalculationService::class)->resolveForClosing([
        [
            'product_id' => $product->id,
            'quantity' => 2,
            'employee_id' => 99,
            'unit_price' => 1,
        ],
    ])->first();

    expect($line['unit_price'])->toBe(25.5)
        ->and($line['line_total'])->toBe(51.0);

    app(InventoryService::class)->record([
        'product_id' => $product->id,
        'business_date' => now()->toDateString(),
        'movement_type' => 'shift_other_product_sale',
        'quantity_out' => $line['quantity'],
        'unit_cost' => $line['unit_cost'],
        'total_cost' => $line['total_cost'],
        'source_type' => 'test',
        'source_id' => 1,
        'source_line_id' => 1,
        'idempotency_key' => 'test-other-product-movement',
    ]);

    expect((float) DB::table('stocks')
        ->where('product_id', $product->id)
        ->value('current_stock'))->toBe(8.0)
        ->and(DB::table('inventory_movements')
            ->where('product_id', $product->id)
            ->where('movement_type', 'shift_other_product_sale')
            ->exists())->toBeTrue();
});
