<?php

use App\Helpers\ErpHelper;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CatalogReferenceService;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->unique();
        $table->string('code', 32)->unique();
        $table->string('inventory_class')->default('fuel');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
});

it('resolves all reserved category codes from ERP configuration', function () {
    expect(ErpHelper::oilCategoryCode())->toBe('1001')
        ->and(ErpHelper::gasCategoryCode())->toBe('1002')
        ->and(ErpHelper::lubricantCategoryCode())->toBe('1003')
        ->and(ErpHelper::getCategoryCode('oil'))->toBe('1001')
        ->and(ErpHelper::getReservedCategoryCodes())
        ->toBe(['1001', '1002', '1003']);
});

it('synchronizes missing reserved categories without overwriting existing names', function () {
    Category::query()->create([
        'code' => ErpHelper::oilCategoryCode(),
        'name' => 'Custom Oil Name',
        'status' => false,
    ]);

    $seeder = new CategorySeeder;
    $seeder->run();
    $seeder->run();

    expect(Category::query()->count())->toBe(3)
        ->and(Category::query()
            ->where('code', ErpHelper::oilCategoryCode())
            ->value('name'))
        ->toBe('Custom Oil Name')
        ->and(Category::query()
            ->where('code', ErpHelper::gasCategoryCode())
            ->value('name'))
        ->toBe('Gas')
        ->and(Category::query()
            ->where('code', ErpHelper::lubricantCategoryCode())
            ->value('name'))
        ->toBe('Lubricant & Accessories');
});

it('accepts only configured codes during category creation', function () {
    $request = CategoryRequest::create('/categories', 'POST');

    $valid = Validator::make([
        'name' => 'Oil',
        'code' => ErpHelper::oilCategoryCode(),
        'status' => true,
    ], $request->rules(), $request->messages());

    $invalid = Validator::make([
        'name' => 'Invalid Category',
        'code' => '9999',
        'status' => true,
    ], $request->rules(), $request->messages());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->first('code'))
        ->toBe('The selected category code is not a reserved ERP category code.');
});

it('ignores code and inventory class in update validation', function () {
    $request = CategoryRequest::create('/categories/1', 'PUT');
    $validator = Validator::make([
        'name' => 'Renamed Category',
        'status' => false,
        'code' => '9999',
        'inventory_class' => 'service',
    ], $request->rules(), $request->messages());

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated())->toBe([
            'name' => 'Renamed Category',
            'status' => false,
        ]);
});

it('keeps a category code immutable after creation', function () {
    $category = Category::query()->create([
        'code' => ErpHelper::oilCategoryCode(),
        'name' => 'Oil',
        'status' => true,
    ]);

    $category->update([
        'code' => '9999',
        'name' => 'Updated Oil',
    ]);

    expect($category->refresh()->code)->toBe(ErpHelper::oilCategoryCode())
        ->and($category->name)->toBe('Updated Oil');
});

it('rejects non-configured codes at the model boundary', function () {
    expect(fn () => Category::query()->create([
        'code' => '9999',
        'name' => 'Invalid Category',
        'status' => true,
    ]))->toThrow(ValidationException::class);
});

it('prevents reserved categories from being deleted directly or through the catalog service', function () {
    $category = Category::query()->create([
        'code' => ErpHelper::oilCategoryCode(),
        'name' => 'Oil',
        'status' => true,
    ]);

    expect(fn () => $category->delete())
        ->toThrow(ValidationException::class)
        ->and(fn () => (new CatalogReferenceService)
            ->deleteCategory($category))
        ->toThrow(ValidationException::class)
        ->and(Category::query()->whereKey($category)->exists())
        ->toBeTrue();
});
