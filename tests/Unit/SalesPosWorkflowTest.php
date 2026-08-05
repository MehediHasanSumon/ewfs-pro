<?php

use App\Http\Requests\SaleRequest;
use App\Http\Resources\SaleEditResource;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Vehicle;
use App\Services\AccountingService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\OperationalReportService;
use App\Services\PartyLedgerService;
use App\Services\PaymentAccountService;
use App\Services\SalePostingService;
use App\Services\SaleProductCatalogService;
use App\Services\SalesCustomerService;
use App\Services\SystemAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('groups', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->string('account_class');
        $table->string('normal_balance');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('group_id');
        $table->string('ac_number')->unique();
        $table->string('name');
        $table->string('semantic_code')->nullable();
        $table->string('currency')->default('BDT');
        $table->boolean('is_control_account')->default(false);
        $table->boolean('allow_manual_posting')->default(true);
        $table->boolean('is_system')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->string('mobile')->nullable();
        $table->text('address')->nullable();
        $table->decimal('discount_rate', 7, 4)->default(0);
        $table->decimal('credit_limit', 24, 4)->default(0);
        $table->unsignedSmallInteger('credit_days')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vehicles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->nullable();
        $table->string('vehicle_type')->nullable();
        $table->string('vehicle_name')->nullable();
        $table->string('vehicle_number');
        $table->date('reg_date')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('code');
        $table->string('inventory_class')->default('merchandise');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('units', function (Blueprint $table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->string('value');
        $table->unsignedTinyInteger('quantity_scale')->default(3);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('category_id');
        $table->foreignId('unit_id');
        $table->string('product_code')->nullable();
        $table->string('product_name');
        $table->boolean('is_inventory_item')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('product_rates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->decimal('purchase_price', 24, 6)->nullable();
        $table->decimal('sales_price', 24, 6)->nullable();
        $table->date('effective_date');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('stocks', function (Blueprint $table) {
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

    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->string('entry_no')->unique();
        $table->date('business_date');
        $table->timestamp('occurred_at');
        $table->string('event_type');
        $table->string('source_type');
        $table->unsignedBigInteger('source_id')->nullable();
        $table->string('reference_no')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('draft');
        $table->string('idempotency_key')->unique();
        $table->timestamp('posted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('journal_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_entry_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('account_id');
        $table->decimal('debit_amount', 24, 4)->default(0);
        $table->decimal('credit_amount', 24, 4)->default(0);
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('product_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('description')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('shifts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    DB::table('shifts')->insert([
        'id' => 1,
        'name' => 'Morning Shift',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shift_id');
        $table->foreignId('customer_id')->nullable();
        $table->foreignId('vehicle_id')->nullable();
        $table->foreignId('journal_entry_id')->nullable();
        $table->string('sale_type')->default('regular');
        $table->date('sale_date');
        $table->time('sale_time');
        $table->string('invoice_no')->unique();
        $table->string('memo_no')->nullable();
        $table->string('customer_name_snapshot')->nullable();
        $table->string('customer_mobile_snapshot')->nullable();
        $table->text('customer_address_snapshot')->nullable();
        $table->string('company_name_snapshot')->nullable();
        $table->string('proprietor_name_snapshot')->nullable();
        $table->string('vehicle_number_snapshot')->nullable();
        $table->decimal('subtotal', 24, 4);
        $table->decimal('discount_total', 24, 4)->default(0);
        $table->decimal('tax_total', 24, 4)->default(0);
        $table->decimal('grand_total', 24, 4);
        $table->string('status')->default('draft');
        $table->boolean('is_send_sms')->default(false);
        $table->text('remarks')->nullable();
        $table->foreignId('created_by')->nullable();
        $table->foreignId('posted_by')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id');
        $table->unsignedSmallInteger('line_no');
        $table->foreignId('product_id');
        $table->foreignId('category_id');
        $table->foreignId('unit_id');
        $table->string('product_code_snapshot')->nullable();
        $table->string('product_name_snapshot');
        $table->string('category_name_snapshot');
        $table->string('unit_name_snapshot');
        $table->decimal('quantity', 24, 6);
        $table->decimal('unit_price', 24, 6);
        $table->decimal('unit_cost', 24, 6)->default(0);
        $table->decimal('discount_amount', 24, 4)->default(0);
        $table->decimal('tax_amount', 24, 4)->default(0);
        $table->decimal('line_total', 24, 4);
        $table->text('remarks')->nullable();
        $table->timestamps();
    });

    Schema::create('sale_payment_details', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id')->unique();
        $table->foreignId('account_id');
        $table->string('payment_method');
        $table->string('bank_type')->nullable();
        $table->string('bank_name')->nullable();
        $table->string('branch_name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('cheque_number')->nullable();
        $table->date('cheque_date')->nullable();
        $table->string('mobile_bank_name')->nullable();
        $table->string('mobile_number')->nullable();
        $table->timestamps();
    });

    Schema::create('sale_payment_allocations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id');
        $table->decimal('amount', 24, 4);
        $table->timestamps();
    });
});

function createPosAccount(
    string $groupCode,
    string $groupName,
    string $accountClass,
    string $accountName
): Account {
    $groupId = DB::table('groups')->insertGetId([
        'code' => $groupCode,
        'name' => $groupName,
        'account_class' => $accountClass,
        'normal_balance' => $accountClass === 'revenue' ? 'credit' : 'debit',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Account::query()->create([
        'group_id' => $groupId,
        'ac_number' => 'AC-'.$groupCode,
        'name' => $accountName,
        'currency' => 'BDT',
        'status' => true,
    ]);
}

function createPosProduct(
    int $categoryId,
    int $unitId,
    string $name,
    ?float $salesPrice,
    bool $status = true
): Product {
    $product = Product::query()->create([
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'product_code' => strtoupper(str_replace(' ', '-', $name)),
        'product_name' => $name,
        'is_inventory_item' => false,
        'status' => $status,
    ]);

    if ($salesPrice !== null) {
        DB::table('product_rates')->insert([
            'product_id' => $product->id,
            'purchase_price' => 5,
            'sales_price' => $salesPrice,
            'effective_date' => '2026-07-28',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $product;
}

function createPosCategory(
    string $name,
    string $code,
    string $inventoryClass = 'merchandise',
    bool $status = true
): int {
    return DB::table('categories')->insertGetId([
        'name' => $name,
        'code' => $code,
        'inventory_class' => $inventoryClass,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeSalesPosFixture(): array
{
    $cash = createPosAccount(
        '100020002',
        'Cash in hand',
        'asset',
        'Office Cash'
    );
    $bank = createPosAccount(
        '100020004',
        'Bank Account',
        'asset',
        'Test Bank'
    );
    $receivable = createPosAccount(
        '100020001',
        'Customer Receivables',
        'asset',
        'Customer Control'
    );
    $revenue = createPosAccount(
        '400010001',
        'Sales Revenue',
        'revenue',
        'Sales Revenue'
    );
    $inventory = createPosAccount(
        '100030001',
        'Inventory',
        'asset',
        'Inventory Asset'
    );
    $cogs = createPosAccount(
        '500010001',
        'Cost of Goods Sold',
        'expense',
        'Cost of Goods Sold'
    );

    $customer = Customer::query()->create([
        'account_id' => $receivable->id,
        'code' => 'CC001',
        'name' => 'Rahim',
        'mobile' => '01711111111',
        'address' => 'Dhaka',
        'status' => true,
    ]);
    $otherCustomer = Customer::query()->create([
        'account_id' => $receivable->id,
        'code' => 'CC002',
        'name' => 'Karim',
        'mobile' => '01811111111',
        'status' => true,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $otherCustomer->id,
        'vehicle_name' => 'Delivery Van',
        'vehicle_number' => 'XYZ-123',
        'status' => true,
    ]);
    $categoryIds = [
        'fuel' => createPosCategory('Fuel', 'FUEL', 'fuel'),
        'lubricant' => createPosCategory('Lubricants', 'LUBRICANT'),
        'accessory' => createPosCategory('Accessories', 'ACCESSORY'),
    ];
    $unitId = DB::table('units')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'value' => '1',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $products = [
        createPosProduct($categoryIds['fuel'], $unitId, 'Petrol', 10.125),
        createPosProduct($categoryIds['lubricant'], $unitId, 'Engine Oil', 20),
        createPosProduct($categoryIds['accessory'], $unitId, 'Wiper Blade', 5.555),
    ];

    $numbers = Mockery::mock(DocumentNumberService::class);
    $numbers->shouldReceive('next')
        ->zeroOrMoreTimes()
        ->andReturn('IN0001');
    $customers = new SalesCustomerService;
    $accounting = Mockery::mock(AccountingService::class);
    $journalNumber = 0;
    $accounting->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (array $entry, array $lines) use (&$journalNumber) {
            $journalNumber++;
            $journalId = DB::table('journal_entries')->insertGetId([
                'entry_no' => 'JRN'.$journalNumber,
                'business_date' => $entry['business_date'],
                'occurred_at' => now(),
                'event_type' => $entry['event_type'],
                'source_type' => $entry['source_type'],
                'source_id' => $entry['source_id'],
                'reference_no' => $entry['reference_no'],
                'description' => $entry['description'],
                'status' => 'posted',
                'idempotency_key' => $entry['idempotency_key'],
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'customer_id' => $line['customer_id'] ?? null,
                    'product_id' => $line['product_id'] ?? null,
                    'payment_method' => $line['payment_method'] ?? null,
                    'description' => $line['description'] ?? null,
                    'created_at' => now(),
                ]);
            }

            return JournalEntry::query()->findOrFail($journalId);
        });
    $accounting->shouldReceive('reverse')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (JournalEntry $entry) {
            $entry->update(['status' => 'reversed']);

            return $entry->fresh();
        });
    $inventoryService = Mockery::mock(InventoryService::class);
    $inventoryService->shouldReceive('assertAvailable')->zeroOrMoreTimes();
    $inventoryService->shouldReceive('recordMany')->zeroOrMoreTimes();
    $inventoryService->shouldReceive('record')->zeroOrMoreTimes();
    $inventoryService->shouldReceive('reverseSource')->zeroOrMoreTimes();
    $systemAccounts = Mockery::mock(SystemAccountService::class);
    $systemAccounts->shouldReceive('salesRevenue')
        ->zeroOrMoreTimes()
        ->andReturn($revenue);
    $systemAccounts->shouldReceive('inventoryAsset')
        ->zeroOrMoreTimes()
        ->andReturn($inventory);
    $systemAccounts->shouldReceive('costOfGoodsSold')
        ->zeroOrMoreTimes()
        ->andReturn($cogs);

    $service = new SalePostingService(
        $accounting,
        $inventoryService,
        $systemAccounts,
        $numbers,
        $customers,
        new PaymentAccountService,
        new SaleProductCatalogService
    );

    return compact(
        'service',
        'customers',
        'customer',
        'otherCustomer',
        'vehicle',
        'products',
        'cash',
        'bank',
        'categoryIds',
        'unitId',
        'inventoryService'
    );
}

function regularPosPayload(array $fixture, array $overrides = []): array
{
    $payload = array_replace_recursive([
        'sale_date' => '2026-07-28',
        'shift_id' => 1,
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'customer_mobile' => $fixture['customer']->mobile,
        'vehicle_id' => $fixture['vehicle']->id,
        'vehicle_no' => $fixture['vehicle']->vehicle_number,
        'memo_no' => null,
        'payment_type' => 'Cash',
        'to_account_id' => $fixture['cash']->id,
        'remarks' => null,
        'items' => [
            [
                'product_id' => $fixture['products'][0]->id,
                'quantity' => 1,
                'discount' => 0,
            ],
            [
                'product_id' => $fixture['products'][1]->id,
                'quantity' => 2,
                'discount' => 1,
            ],
            [
                'product_id' => $fixture['products'][2]->id,
                'quantity' => 3,
                'discount' => 0,
            ],
        ],
    ], $overrides);

    if (array_key_exists('items', $overrides)) {
        $payload['items'] = $overrides['items'];
    }

    return $payload;
}

it('keeps removed modal fields out of the sale validation contract', function () {
    $rules = (new SaleRequest)->rules();

    foreach ([
        'customer_address',
        'previous_due',
        'save_customer',
        'paid_amount',
    ] as $field) {
        expect(array_key_exists($field, $rules))->toBeFalse();
    }
});

it('looks up a normalized mobile with active vehicles', function () {
    $fixture = makeSalesPosFixture();
    Vehicle::query()->create([
        'customer_id' => $fixture['customer']->id,
        'vehicle_number' => 'DHAKA-1001',
        'status' => true,
    ]);

    $customer = $fixture['customers']->lookup('017-1111-1111');

    expect($customer?->id)->toBe($fixture['customer']->id)
        ->and($customer?->vehicles->pluck('vehicle_number')->all())
        ->toBe(['DHAKA-1001']);
});

it('rejects a selected customer when the submitted mobile belongs elsewhere', function () {
    $fixture = makeSalesPosFixture();

    expect(fn () => $fixture['customers']->resolve([
        'customer_id' => $fixture['customer']->id,
        'customer_mobile' => $fixture['otherCustomer']->mobile,
    ]))->toThrow(ValidationException::class);
});

it('posts one voucher with multiple global products at master prices', function () {
    $fixture = makeSalesPosFixture();

    $sale = $fixture['service']->create(regularPosPayload($fixture));

    expect(DB::table('sales')->count())->toBe(1)
        ->and($sale->items)->toHaveCount(3)
        ->and((float) $sale->subtotal)->toBe(66.8)
        ->and((float) $sale->grand_total)->toBe(65.8)
        ->and($sale->customer_id)->toBe($fixture['customer']->id)
        ->and($sale->vehicle_id)->toBe($fixture['vehicle']->id)
        ->and($sale->vehicle?->customer_id)->toBe($fixture['otherCustomer']->id)
        ->and($sale->journalEntry->lines->whereNotNull('customer_id'))
        ->toHaveCount(0)
        ->and($sale->items->pluck('unit_price')->map(fn ($price) => (float) $price)->all())
        ->toBe([10.125, 20.0, 5.555])
        ->and($sale->items->pluck('category_name_snapshot')->all())
        ->toBe(['Fuel', 'Lubricants', 'Accessories'])
        ->and((float) $sale->journalEntry->lines->sum('debit_amount'))
        ->toBe(65.8)
        ->and(round(
            (float) $sale->journalEntry->lines->sum('credit_amount'),
            2
        ))
        ->toBe(65.8)
        ->and($sale->paymentDetail?->payment_method)->toBe('cash');

    $report = app(OperationalReportService::class)
        ->customerSales('2026-07-28', '2026-07-28');

    expect($report)->toHaveCount(1)
        ->and($report->first()['invoice_no'])->toBe($sale->invoice_no)
        ->and($report->first()['quantity'])->toBe(6.0)
        ->and($report->first()['total_amount'])->toBe(65.8);

    $metric = app(PartyLedgerService::class)
        ->customerMetrics(collect([$fixture['customer']]))
        ->get($fixture['customer']->id);

    expect($metric['total_sales'])->toBe(0.0)
        ->and($metric['total_paid'])->toBe(0.0)
        ->and($metric['current_due'])->toBe(0.0)
        ->and($metric['current_advance'])->toBe(0.0);
});

it('exposes every active sales category without dispenser or vehicle restrictions', function () {
    $fixture = makeSalesPosFixture();
    $futureCategoryId = createPosCategory('Services', 'FUTURE-SERVICE', 'service');
    $futureProduct = createPosProduct(
        $futureCategoryId,
        $fixture['unitId'],
        'Wheel Alignment',
        250
    );
    $inactiveProduct = createPosProduct(
        $fixture['categoryIds']['accessory'],
        $fixture['unitId'],
        'Inactive Accessory',
        25,
        false
    );
    $inactiveCategoryId = createPosCategory(
        'Archived Parts',
        'ARCHIVED-PARTS',
        'merchandise',
        false
    );
    $inactiveCategoryProduct = createPosProduct(
        $inactiveCategoryId,
        $fixture['unitId'],
        'Archived Part',
        75
    );

    $catalog = (new SaleProductCatalogService)->forSelection();
    $visibleIds = $catalog->pluck('id')->all();
    $expectedActiveIds = [
        ...array_map(
            fn (Product $product) => $product->id,
            $fixture['products']
        ),
        $futureProduct->id,
    ];

    expect(array_diff($expectedActiveIds, $visibleIds))->toBe([])
        ->and($visibleIds)->not->toContain($inactiveProduct->id)
        ->not->toContain($inactiveCategoryProduct->id)
        ->and($catalog->firstWhere('id', $futureProduct->id)['category']['name'])
        ->toBe('Services');

    expect(fn () => $fixture['service']->create(
        regularPosPayload($fixture, [
            'items' => [[
                'product_id' => $inactiveCategoryProduct->id,
                'quantity' => 1,
                'discount' => 0,
            ]],
        ])
    ))->toThrow(ValidationException::class);
});

it('honors explicitly configured sales category exclusions', function () {
    $fixture = makeSalesPosFixture();
    config()->set('erp.sales.excluded_category_codes', ['ACCESSORY']);

    $catalog = new SaleProductCatalogService;
    $visibleIds = $catalog->forSelection()->pluck('id')->all();

    expect($visibleIds)
        ->toContain($fixture['products'][0]->id)
        ->toContain($fixture['products'][1]->id)
        ->not->toContain($fixture['products'][2]->id);

    expect(fn () => $fixture['service']->create(
        regularPosPayload($fixture, [
            'items' => [[
                'product_id' => $fixture['products'][2]->id,
                'quantity' => 1,
                'discount' => 0,
            ]],
        ])
    ))->toThrow(ValidationException::class);
});

it('uses the active master sales price instead of any submitted client price', function () {
    $fixture = makeSalesPosFixture();
    $payload = regularPosPayload($fixture, [
        'items' => [[
            'product_id' => $fixture['products'][1]->id,
            'quantity' => 2,
            'discount' => 0,
            'unit_price' => 0.01,
            'sales_price' => 0.01,
        ]],
    ]);

    $sale = $fixture['service']->create($payload);

    expect((float) $sale->items->first()->unit_price)->toBe(20.0)
        ->and((float) $sale->grand_total)->toBe(40.0);
});

it('issues fuel inventory immediately when a regular cash sale is posted', function () {
    $fixture = makeSalesPosFixture();
    $fixture['products'][0]->update([
        'is_inventory_item' => true,
    ]);
    $payload = regularPosPayload($fixture, [
        'items' => [[
            'product_id' => $fixture['products'][0]->id,
            'quantity' => 2,
            'discount' => 0,
        ]],
    ]);

    $fixture['service']->create($payload);

    $fixture['inventoryService']
        ->shouldHaveReceived('assertAvailable')
        ->once()
        ->with([
            $fixture['products'][0]->id => 2.0,
        ]);
    $fixture['inventoryService']
        ->shouldHaveReceived('recordMany')
        ->once()
        ->with(Mockery::on(
            fn (array $movements) => count($movements) === 1
                && $movements[0]['product_id']
                    === $fixture['products'][0]->id
                && $movements[0]['movement_type'] === 'regular_sale'
                && (float) $movements[0]['quantity_out'] === 2.0
        ));
});

it('stores walk-in identity as sale snapshots without creating party records', function () {
    $fixture = makeSalesPosFixture();
    $customerCount = Customer::query()->count();
    $vehicleCount = Vehicle::query()->count();
    $accountCount = Account::query()->count();
    $payload = regularPosPayload($fixture, [
        'customer_id' => null,
        'customer_name' => 'New POS Customer',
        'customer_mobile' => '01911-111111',
        'vehicle_id' => null,
        'vehicle_no' => 'NEW-VEHICLE',
        'items' => [[
            'product_id' => $fixture['products'][0]->id,
            'quantity' => 1,
            'discount' => 0,
        ]],
    ]);

    $sale = $fixture['service']->create($payload);

    expect($sale->customer_id)->toBeNull()
        ->and($sale->vehicle_id)->toBeNull()
        ->and($sale->customer_name_snapshot)->toBe('New POS Customer')
        ->and($sale->customer_mobile_snapshot)->toBe('01911111111')
        ->and($sale->vehicle_number_snapshot)->toBe('NEW-VEHICLE')
        ->and(Customer::query()->count())->toBe($customerCount)
        ->and(Vehicle::query()->count())->toBe($vehicleCount)
        ->and(Account::query()->count())->toBe($accountCount)
        ->and(Customer::query()->where('mobile', '01911111111')->exists())
        ->toBeFalse();
});

it('reuses a permanent customer by normalized mobile and updates editable details', function () {
    $fixture = makeSalesPosFixture();
    $customerCount = Customer::query()->count();
    $resolved = DB::transaction(fn () => $fixture['customers']->resolve([
        'customer_id' => null,
        'customer_name' => 'Updated Rahim',
        'customer_mobile' => '017-1111-1111',
    ]));

    expect($resolved?->id)->toBe($fixture['customer']->id)
        ->and($resolved?->fresh()->name)->toBe('Updated Rahim')
        ->and(Customer::query()->count())->toBe($customerCount);
});

it('rejects a payment account that does not match the payment method', function () {
    $fixture = makeSalesPosFixture();
    $payload = regularPosPayload($fixture, [
        'to_account_id' => $fixture['bank']->id,
    ]);

    try {
        $fixture['service']->create($payload);
        $this->fail('Expected payment account validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('to_account_id')
            ->and(DB::table('sales')->count())->toBe(0);
    }
});

it('does not persist walk-in party data when sale posting fails', function () {
    $fixture = makeSalesPosFixture();
    $customerCount = Customer::query()->count();
    $accountCount = Account::query()->count();
    $payload = regularPosPayload($fixture, [
        'customer_id' => null,
        'customer_name' => 'Rollback Customer',
        'customer_mobile' => '01633-333333',
        'vehicle_id' => null,
        'vehicle_no' => null,
        'to_account_id' => $fixture['bank']->id,
        'items' => [[
            'product_id' => $fixture['products'][0]->id,
            'quantity' => 1,
            'discount' => 0,
        ]],
    ]);

    try {
        $fixture['service']->create($payload);
        $this->fail('Expected payment account validation to fail.');
    } catch (ValidationException) {
        expect(Customer::query()->count())->toBe($customerCount)
            ->and(Account::query()->count())->toBe($accountCount)
            ->and(Customer::query()->where('mobile', '01633333333')->exists())
            ->toBeFalse()
            ->and(DB::table('sales')->count())->toBe(0);
    }
});

it('edits the same voucher and replaces only its item set', function () {
    $fixture = makeSalesPosFixture();
    $sale = $fixture['service']->create(regularPosPayload($fixture));
    $originalId = $sale->id;
    $originalInvoice = $sale->invoice_no;
    $originalJournalId = $sale->journal_entry_id;
    $replacement = regularPosPayload($fixture, [
        'items' => [
            [
                'product_id' => $fixture['products'][0]->id,
                'quantity' => 2,
                'discount' => 0,
            ],
            [
                'product_id' => $fixture['products'][2]->id,
                'quantity' => 1,
                'discount' => 0,
            ],
        ],
    ]);

    $updated = $fixture['service']->replace($sale, $replacement);

    expect($updated->id)->toBe($originalId)
        ->and($updated->invoice_no)->toBe($originalInvoice)
        ->and($updated->items)->toHaveCount(2)
        ->and($updated->items->pluck('product_id')->all())->toBe([
            $fixture['products'][0]->id,
            $fixture['products'][2]->id,
        ])
        ->and((float) $updated->grand_total)->toBe(25.81)
        ->and(DB::table('sales')->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('id', $originalJournalId)->value('status'))
        ->toBe('reversed')
        ->and($updated->journalEntry?->status)->toBe('posted');
});

it('falls back to the posted journal payment line for legacy sale edits', function () {
    $fixture = makeSalesPosFixture();
    $sale = $fixture['service']->create(regularPosPayload($fixture));
    $sale->paymentDetail()->delete();
    $sale->load([
        'items',
        'paymentDetail',
        'transaction.account',
    ]);

    $resource = (new SaleEditResource($sale))->resolve();

    expect($resource['payment']['payment_type'])->toBe('cash')
        ->and($resource['payment']['to_account_id'])->toBe($fixture['cash']->id)
        ->and($resource['payment']['account_no'])->toBe($fixture['cash']->ac_number)
        ->and($resource)->not->toHaveKey('customer_address')
        ->and($resource['payment'])->not->toHaveKey('paid_amount');
});
