<?php

use App\Http\Requests\PurchaseRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\PaymentAccountService;
use App\Services\PurchasePostingService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('groups', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->string('account_class');
        $table->string('normal_balance');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('group_id');
        $table->string('ac_number');
        $table->string('name');
        $table->string('semantic_code')->nullable();
        $table->string('currency')->default('BDT');
        $table->boolean('is_control_account')->default(false);
        $table->boolean('allow_manual_posting')->default(true);
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('suppliers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('account_id');
        $table->string('code')->nullable();
        $table->string('name');
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
        $table->unsignedBigInteger('category_id')->nullable();
        $table->foreignId('unit_id');
        $table->string('product_code')->nullable();
        $table->string('product_name');
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

    Schema::create('journal_entries', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->string('entry_no');
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('event_type');
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('draft');
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key')->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->unsignedBigInteger('reversed_by')->nullable();
        $table->timestamp('reversed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('journal_lines', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('description')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('purchases', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('supplier_id');
        $table->unsignedBigInteger('shift_id')->nullable();
        $table->unsignedBigInteger('journal_entry_id')->nullable();
        $table->string('invoice_no');
        $table->string('supplier_invoice_no')->nullable();
        $table->string('memo_no')->nullable();
        $table->date('purchase_date');
        $table->time('purchase_time')->nullable();
        $table->decimal('subtotal', 24, 4);
        $table->decimal('discount_total', 24, 4)->default(0);
        $table->decimal('tax_total', 24, 4)->default(0);
        $table->decimal('grand_total', 24, 4);
        $table->string('status')->default('draft');
        $table->text('remarks')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('purchase_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('purchase_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('product_id');
        $table->foreignId('unit_id');
        $table->string('product_code_snapshot')->nullable();
        $table->string('product_name_snapshot');
        $table->string('unit_name_snapshot');
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_cost', 24, 6);
        $table->decimal('discount_amount', 24, 4)->default(0);
        $table->decimal('tax_amount', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4);
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
        $table->string('movement_type');
        $table->decimal('quantity_in', 24, 6)->default(0);
        $table->decimal('quantity_out', 24, 6)->default(0);
        $table->decimal('before_stock', 24, 6)->nullable();
        $table->decimal('after_stock', 24, 6)->nullable();
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('total_cost', 24, 4)->default(0);
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->unsignedBigInteger('source_line_id')->nullable();
        $table->text('remarks')->nullable();
        $table->unsignedBigInteger('reversal_of_id')->nullable();
        $table->string('idempotency_key')->unique();
        $table->unsignedBigInteger('posted_by')->nullable();
        $table->timestamp('created_at')->nullable();
    });
});

afterEach(function () {
    foreach ([
        'inventory_movements',
        'stocks',
        'purchase_items',
        'purchases',
        'journal_lines',
        'journal_entries',
        'product_rates',
        'products',
        'units',
        'suppliers',
        'accounts',
        'groups',
    ] as $table) {
        Schema::dropIfExists($table);
    }
});

function purchaseWorkflowFixture(
    ?AccountingService $accounting = null
): array {
    $liabilityGroup = DB::table('groups')->insertGetId([
        'code' => '200010001',
        'name' => 'Supplier Payables',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $assetGroup = DB::table('groups')->insertGetId([
        'code' => '100030001',
        'name' => 'Inventory',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $supplierAccount = Account::query()->create([
        'group_id' => $liabilityGroup,
        'ac_number' => 'SUP-ACCOUNT',
        'name' => 'Supplier Account',
        'status' => true,
    ]);
    $inventoryAccount = Account::query()->create([
        'group_id' => $assetGroup,
        'ac_number' => 'INVENTORY',
        'name' => 'Inventory Asset',
        'status' => true,
    ]);
    $supplier = Supplier::query()->create([
        'account_id' => $supplierAccount->id,
        'code' => 'SUP001',
        'name' => 'Test Supplier',
        'status' => true,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'name' => 'Litre',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $product = Product::query()->create([
        'unit_id' => $unitId,
        'product_code' => 'P001',
        'product_name' => 'Test Product',
        'is_inventory_item' => true,
        'status' => true,
    ]);
    DB::table('product_rates')->insert([
        'product_id' => $product->id,
        'purchase_price' => 10.25,
        'sales_price' => 12,
        'effective_date' => '2026-08-03',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $accounting ??= Mockery::mock(AccountingService::class);
    $accounting->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $entryData, array $lines) {
            $entry = JournalEntry::query()->create([
                'entry_no' => 'JRN-'.uniqid(),
                'business_date' => $entryData['business_date'],
                'occurred_at' => now(),
                'event_type' => $entryData['event_type'],
                'source_type' => $entryData['source_type'],
                'source_id' => $entryData['source_id'],
                'reference_no' => $entryData['reference_no'],
                'description' => $entryData['description'],
                'status' => 'posted',
                'idempotency_key' => $entryData['idempotency_key'],
                'posted_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                $entry->lines()->create([
                    'line_no' => $index + 1,
                    ...$line,
                ]);
            }

            return $entry->fresh('lines');
        });
    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')
        ->zeroOrMoreTimes()
        ->andReturn('IN0001');
    $systemAccounts = Mockery::mock(SystemAccountService::class);
    $systemAccounts->shouldReceive('inventoryAsset')
        ->zeroOrMoreTimes()
        ->andReturn($inventoryAccount);
    $service = new PurchasePostingService(
        $accounting,
        app(InventoryService::class),
        $systemAccounts,
        $numbers,
        new PaymentAccountService
    );

    return compact('service', 'supplier', 'product');
}

function duePurchasePayload(array $fixture): array
{
    return [
        'purchase_date' => '2026-08-03',
        'memo_no' => 'PUR-001',
        'remarks' => 'Due purchase',
        'products' => [[
            'product_id' => $fixture['product']->id,
            'supplier_id' => $fixture['supplier']->id,
            'unit_price' => 10.25,
            'quantity' => 3,
            'discount' => 0,
            'payment_type' => 'Cash',
            'paid_amount' => 0,
        ]],
    ];
}

it('posts a due purchase without a payment account and increases stock atomically', function () {
    $fixture = purchaseWorkflowFixture();
    $purchase = $fixture['service']->createMany(
        duePurchasePayload($fixture)
    )[0];

    expect((float) $purchase->grand_total)->toBe(30.75)
        ->and($purchase->status)->toBe('posted')
        ->and(DB::table('journal_entries')->count())->toBe(1)
        ->and(DB::table('journal_lines')->count())->toBe(2)
        ->and((float) DB::table('stocks')->value('current_stock'))->toBe(3.0)
        ->and((float) DB::table('inventory_movements')
            ->value('quantity_in'))->toBe(3.0)
        ->and((float) DB::table('inventory_movements')
            ->value('before_stock'))->toBe(0.0)
        ->and((float) DB::table('inventory_movements')
            ->value('after_stock'))->toBe(3.0);
});

it('rejects a zero purchase total before creating partial records', function () {
    $fixture = purchaseWorkflowFixture();
    $payload = duePurchasePayload($fixture);
    $payload['products'][0]['discount'] = 30.75;

    expect(fn () => $fixture['service']->createMany($payload))
        ->toThrow(ValidationException::class);

    expect(DB::table('purchases')->count())->toBe(0)
        ->and(DB::table('purchase_items')->count())->toBe(0)
        ->and(DB::table('journal_entries')->count())->toBe(0)
        ->and(DB::table('inventory_movements')->count())->toBe(0);
});

it('keeps client calculated amount and due fields out of the purchase contract', function () {
    $rules = (new PurchaseRequest)->rules();

    expect(array_key_exists('products.*.amount', $rules))->toBeFalse()
        ->and(array_key_exists('products.*.due_amount', $rules))->toBeFalse()
        ->and($rules)->toHaveKeys([
            'products.*.unit_price',
            'products.*.quantity',
            'products.*.paid_amount',
        ]);
});
