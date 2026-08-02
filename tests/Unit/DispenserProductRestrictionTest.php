<?php

use App\Helpers\ErpHelper;
use App\Http\Requests\DispenserRequest;
use App\Models\Dispenser;
use App\Models\Product;
use App\Services\DispenserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
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

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id');
        $table->string('product_name', 150);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('dispensers', function (Blueprint $table): void {
        $table->id();
        $table->string('code', 64)->nullable();
        $table->string('dispenser_name', 150);
        $table->foreignId('product_id');
        $table->unsignedInteger('dispenser_item')->nullable();
        $table->decimal('opening_reading', 24, 6)->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('dispenser_readings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('dispenser_id');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('dispenser_readings');
    Schema::dropIfExists('dispensers');
    Schema::dropIfExists('products');
    Schema::dropIfExists('categories');
});

function createDispenserRestrictionFixture(): array
{
    $categories = collect([
        ['name' => 'Oil', 'code' => ErpHelper::oilCategoryCode()],
        ['name' => 'Gas', 'code' => ErpHelper::gasCategoryCode()],
        ['name' => 'Lubricant', 'code' => ErpHelper::lubricantCategoryCode()],
    ])->mapWithKeys(function (array $category): array {
        $category['created_at'] = now();
        $category['updated_at'] = now();

        return [
            $category['code'] => DB::table('categories')->insertGetId($category),
        ];
    });

    $products = collect([
        'oil' => ['name' => 'Premium Oil', 'category' => $categories[ErpHelper::oilCategoryCode()]],
        'gas' => ['name' => 'Regular Gas', 'category' => $categories[ErpHelper::gasCategoryCode()]],
        'lubricant' => [
            'name' => 'Engine Lubricant',
            'category' => $categories[ErpHelper::lubricantCategoryCode()],
        ],
    ])->mapWithKeys(function (array $product, string $key): array {
        return [
            $key => Product::query()->create([
                'category_id' => $product['category'],
                'product_name' => $product['name'],
                'status' => true,
            ]),
        ];
    });

    return compact('categories', 'products');
}

it('resolves only configured Oil and Gas products for dispenser assignment', function (): void {
    $fixture = createDispenserRestrictionFixture();

    expect(Product::query()
        ->allowedForDispenser()
        ->pluck('id')
        ->sort()
        ->values()
        ->all())
        ->toBe([
            $fixture['products']['oil']->id,
            $fixture['products']['gas']->id,
        ]);
});

it('rejects invalid categories at request and service boundaries', function (): void {
    $fixture = createDispenserRestrictionFixture();
    $request = DispenserRequest::create('/dispensers', 'POST', [
        'dispenser_name' => 'Invalid Pump',
        'product_id' => $fixture['products']['lubricant']->id,
        'status' => true,
    ]);
    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('product_id'))
        ->toContain(
            'The selected product category is not allowed for dispenser assignment.'
        )
        ->and(fn () => app(DispenserService::class)->create([
            'dispenser_name' => 'Invalid Pump',
            'product_id' => $fixture['products']['lubricant']->id,
            'status' => true,
        ]))
        ->toThrow(ValidationException::class);
});

it('creates valid dispensers and allows correction of a legacy invalid assignment', function (): void {
    $fixture = createDispenserRestrictionFixture();
    $service = app(DispenserService::class);

    $dispenser = $service->create([
        'dispenser_name' => 'Oil Pump',
        'product_id' => $fixture['products']['oil']->id,
        'status' => true,
    ]);

    expect($dispenser->product_id)->toBe($fixture['products']['oil']->id);

    $legacyId = DB::table('dispensers')->insertGetId([
        'dispenser_name' => 'Legacy Pump',
        'product_id' => $fixture['products']['lubricant']->id,
        'dispenser_item' => $fixture['products']['lubricant']->id,
        'opening_reading' => 0,
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $legacy = Dispenser::query()->findOrFail($legacyId);

    $updated = $service->update($legacy, [
        'dispenser_name' => 'Legacy Pump',
        'product_id' => $fixture['products']['gas']->id,
        'status' => true,
    ]);

    expect($updated->product_id)->toBe($fixture['products']['gas']->id);
});
