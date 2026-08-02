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
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('total_cost', 24, 4)->default(0);
        $table->string('source_type', 100);
        $table->unsignedBigInteger('source_id')->nullable();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key', 150)->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('created_at')->nullable();
    });
});

afterEach(function (): void {
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
