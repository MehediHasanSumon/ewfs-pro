<?php

use App\Services\InventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('product_name');
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
        $table->decimal('maximum_stock', 24, 6)->nullable();
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

    DB::table('products')->insert([
        'id' => 1,
        'product_name' => 'Inventory Product',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    Schema::dropIfExists('inventory_movements');
    Schema::dropIfExists('stocks');
    Schema::dropIfExists('products');
});

function inventoryMovement(array $overrides = []): array
{
    return array_replace([
        'product_id' => 1,
        'business_date' => '2026-08-03',
        'movement_type' => 'purchase',
        'quantity_in' => 10,
        'unit_cost' => 5,
        'total_cost' => 50,
        'source_type' => 'test',
        'source_id' => 1,
        'source_line_id' => 1,
        'idempotency_key' => 'inventory-test-purchase',
        'remarks' => 'Test movement',
    ], $overrides);
}

it('keeps stock and movement history synchronized with before and after balances', function () {
    $inventory = app(InventoryService::class);
    $purchase = $inventory->record(inventoryMovement());
    $sale = $inventory->record(inventoryMovement([
        'movement_type' => 'cash_sale',
        'quantity_in' => 0,
        'quantity_out' => 4,
        'total_cost' => 20,
        'source_id' => 2,
        'source_line_id' => 2,
        'idempotency_key' => 'inventory-test-cash-sale',
    ]));

    expect((float) $purchase->before_stock)->toBe(0.0)
        ->and((float) $purchase->after_stock)->toBe(10.0)
        ->and((float) $sale->before_stock)->toBe(10.0)
        ->and((float) $sale->after_stock)->toBe(6.0)
        ->and((float) DB::table('stocks')->value('current_stock'))->toBe(6.0)
        ->and((float) DB::table('stocks')->value('available_stock'))->toBe(6.0)
        ->and((float) DB::table('inventory_movements')
            ->selectRaw('SUM(quantity_in - quantity_out) total')
            ->value('total'))->toBe(6.0);
});

it('rolls back the full movement batch and returns the required stock message', function () {
    $inventory = app(InventoryService::class);
    $inventory->record(inventoryMovement());

    try {
        $inventory->recordMany([
            inventoryMovement([
                'movement_type' => 'cash_sale',
                'quantity_in' => 0,
                'quantity_out' => 6,
                'source_id' => 2,
                'source_line_id' => 2,
                'idempotency_key' => 'inventory-batch-sale-1',
            ]),
            inventoryMovement([
                'movement_type' => 'credit_sale',
                'quantity_in' => 0,
                'quantity_out' => 5,
                'source_id' => 3,
                'source_line_id' => 3,
                'idempotency_key' => 'inventory-batch-sale-2',
            ]),
        ]);

        $this->fail('The inventory batch should have failed.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['stock'][0])
            ->toBe('Insufficient stock available.');
    }

    expect((float) DB::table('stocks')->value('current_stock'))->toBe(10.0)
        ->and(DB::table('inventory_movements')->count())->toBe(1);
});

it('does not duplicate stock for an existing idempotency key', function () {
    $inventory = app(InventoryService::class);
    $first = $inventory->record(inventoryMovement());
    $second = $inventory->record(inventoryMovement());

    expect($second->id)->toBe($first->id)
        ->and(DB::table('inventory_movements')->count())->toBe(1)
        ->and((float) DB::table('stocks')->value('current_stock'))->toBe(10.0);
});

it('validates available stock rather than unrestricted current stock', function () {
    $inventory = app(InventoryService::class);
    $inventory->record(inventoryMovement());
    DB::table('stocks')->update([
        'reserved_stock' => 7,
        'available_stock' => 3,
    ]);

    expect(fn () => $inventory->assertAvailable([1 => 4]))
        ->toThrow(
            ValidationException::class,
            'Insufficient stock available.'
        );

    $inventory->assertAvailable([1 => 3]);

    expect((float) DB::table('stocks')->value('current_stock'))->toBe(10.0);
});

it('reverses a source as an idempotent inventory batch', function () {
    $inventory = app(InventoryService::class);
    $inventory->record(inventoryMovement());

    $inventory->reverseSource('test', 1, 'Test reversal');
    $inventory->reverseSource('test', 1, 'Test reversal');

    expect((float) DB::table('stocks')->value('current_stock'))->toBe(0.0)
        ->and(DB::table('inventory_movements')->count())->toBe(2)
        ->and((float) DB::table('inventory_movements')
            ->whereNotNull('reversal_of_id')
            ->value('quantity_out'))->toBe(10.0)
        ->and(DB::table('inventory_movements')
            ->whereNotNull('reversal_of_id')
            ->value('remarks'))->toBe('Test reversal');
});
