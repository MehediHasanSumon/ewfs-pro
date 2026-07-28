<?php

use App\Http\Requests\VehicleRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\VehicleResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vehicle;
use App\Services\VehicleProductAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('proprietor_name')->nullable();
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('product_name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vehicles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id');
        $table->string('vehicle_type')->nullable();
        $table->string('vehicle_name')->nullable();
        $table->string('vehicle_number');
        $table->date('reg_date')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->unique(['customer_id', 'vehicle_number']);
    });

    Schema::create('vehicle_products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vehicle_id');
        $table->foreignId('product_id');
        $table->unsignedInteger('sort_order');
        $table->timestamps();
        $table->unique(['vehicle_id', 'product_id']);
        $table->index(['vehicle_id', 'sort_order']);
    });
});

function createAssignmentFixture(): array
{
    $customerId = DB::table('customers')->insertGetId([
        'name' => 'Acme Transport',
        'proprietor_name' => 'A. Rahman',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productIds = collect(['Diesel', 'Petrol', 'Octane'])
        ->map(fn (string $name) => DB::table('products')->insertGetId([
            'product_name' => $name,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))
        ->all();

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customerId,
        'vehicle_number' => 'DHAKA-1001',
        'status' => true,
    ]);

    return [$customerId, $productIds, $vehicle];
}

it('normalizes arbitrary sort values and persists vehicle-specific order', function () {
    [, $productIds, $vehicle] = createAssignmentFixture();
    $service = app(VehicleProductAssignmentService::class);

    $service->sync($vehicle, [
        ['product_id' => $productIds[0], 'sort_order' => 7],
        ['product_id' => $productIds[1], 'sort_order' => 2],
        ['product_id' => $productIds[2], 'sort_order' => 5],
    ]);

    expect(
        $vehicle->fresh()->products->map(fn (Product $product) => [
            'id' => $product->id,
            'sort_order' => $product->pivot->sort_order,
        ])->all()
    )->toBe([
        ['id' => $productIds[1], 'sort_order' => 1],
        ['id' => $productIds[2], 'sort_order' => 2],
        ['id' => $productIds[0], 'sort_order' => 3],
    ]);
});

it('recalculates order when an assigned product is removed', function () {
    [, $productIds, $vehicle] = createAssignmentFixture();
    $service = app(VehicleProductAssignmentService::class);

    $service->sync($vehicle, [
        ['product_id' => $productIds[0], 'sort_order' => 1],
        ['product_id' => $productIds[1], 'sort_order' => 2],
        ['product_id' => $productIds[2], 'sort_order' => 3],
    ]);
    $service->sync($vehicle, [
        ['product_id' => $productIds[0], 'sort_order' => 1],
        ['product_id' => $productIds[2], 'sort_order' => 3],
    ]);

    expect(
        DB::table('vehicle_products')
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('sort_order')
            ->pluck('sort_order', 'product_id')
            ->all()
    )->toBe([
        $productIds[0] => 1,
        $productIds[2] => 2,
    ]);
});

it('rejects duplicate ordering and inactive products', function () {
    [$customerId, $productIds] = createAssignmentFixture();
    $inactiveProductId = DB::table('products')->insertGetId([
        'product_name' => 'Inactive Oil',
        'status' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $request = VehicleRequest::create('/vehicles', 'POST');
    $request->setContainer(app());

    $validator = Validator::make([
        'customer_id' => $customerId,
        'vehicle_number' => 'DHAKA-2002',
        'products' => [
            ['product_id' => $productIds[0], 'sort_order' => 1],
            ['product_id' => $productIds[0], 'sort_order' => 1],
            ['product_id' => $inactiveProductId, 'sort_order' => 3],
        ],
    ], $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('products.1.product_id'))->toBeTrue()
        ->and($validator->errors()->has('products.1.sort_order'))->toBeTrue()
        ->and($validator->errors()->has('products.2.product_id'))->toBeTrue();
});

it('rejects products that are not assigned to the vehicle', function () {
    [, $productIds, $vehicle] = createAssignmentFixture();
    $service = app(VehicleProductAssignmentService::class);
    $service->sync($vehicle, [
        ['product_id' => $productIds[0], 'sort_order' => 1],
    ]);

    expect(fn () => $service->assertAssigned($vehicle, $productIds[1]))
        ->toThrow(ValidationException::class);
});

it('serializes proprietor name and ordered vehicle products', function () {
    [$customerId, $productIds, $vehicle] = createAssignmentFixture();
    $service = app(VehicleProductAssignmentService::class);
    $service->sync($vehicle, [
        ['product_id' => $productIds[2], 'sort_order' => 1],
        ['product_id' => $productIds[0], 'sort_order' => 2],
    ]);

    $customer = Customer::query()->findOrFail($customerId);
    $vehicle->load(['customer', 'products']);

    expect((new CustomerResource($customer))->resolve()['proprietor_name'])
        ->toBe('A. Rahman')
        ->and(
            collect((new VehicleResource($vehicle))->resolve()['products'])
                ->pluck('id')
                ->all()
        )->toBe([$productIds[2], $productIds[0]]);
});
