<?php

use App\Helpers\VoucherCategoryHelper;
use App\Models\PaymentSubType;
use App\Models\VoucherCategory;
use App\Services\CustomerSecurityDepositService;
use App\Services\VoucherCategoryService;
use Database\Seeders\SystemVoucherCategorySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('voucher_categories', function (Blueprint $table) {
        $table->id();
        $table->string('code', 64)->nullable()->unique();
        $table->string('name', 150)->unique();
        $table->string('report_bucket_code', 100)->nullable();
        $table->text('description')->nullable();
        $table->boolean('status')->default(true);
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
    });

    Schema::create('document_sequences', function (Blueprint $table) {
        $table->id();
        $table->string('document_type', 100);
        $table->string('prefix', 32);
        $table->unsignedInteger('fiscal_year');
        $table->unsignedBigInteger('next_number')->default(1);
        $table->unsignedInteger('version')->default(0);
        $table->timestamps();
        $table->unique(['document_type', 'fiscal_year']);
    });

    Schema::create('payment_sub_types', function (Blueprint $table) {
        $table->id();
        $table->string('code', 64)->unique();
        $table->string('name', 150);
        $table->foreignId('voucher_category_id');
        $table->string('type', 20);
        $table->string('report_bucket_code', 100)->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_category_id')->nullable();
        $table->timestamps();
    });
});

it('resets existing categories and seeds fresh system voucher categories', function () {
    VoucherCategory::query()->create([
        'code' => VoucherCategoryHelper::customerCode(),
        'name' => 'Client Accounts',
        'status' => false,
        'sort_order' => 9,
    ]);
    VoucherCategory::query()->create([
        'code' => 'VC006',
        'name' => 'Old Custom Category',
        'status' => true,
        'sort_order' => 6,
    ]);

    $seeder = new SystemVoucherCategorySeeder;
    $seeder->run();

    expect(VoucherCategory::query()->count())->toBe(5)
        ->and(VoucherCategory::query()
            ->where('code', VoucherCategoryHelper::customerCode())
            ->value('name'))
        ->toBe('Customer')
        ->and(VoucherCategory::query()
            ->where('code', VoucherCategoryHelper::financeCode())
            ->exists())
        ->toBeTrue()
        ->and(VoucherCategory::query()
            ->where('name', 'Old Custom Category')
            ->exists())
        ->toBeFalse()
        ->and(VoucherCategory::query()
            ->where('code', VoucherCategoryHelper::customerCode())
            ->value('is_system'))
        ->toBeTrue();
});

it('can reset and seed repeatedly without creating duplicates', function () {
    $seeder = new SystemVoucherCategorySeeder;
    $seeder->run();
    $seeder->run();

    expect(VoucherCategory::query()->count())->toBe(5)
        ->and(VoucherCategory::query()
            ->pluck('code')
            ->sort()
            ->values()
            ->all())
        ->toBe(VoucherCategoryHelper::getSystemCategoryCodes());
});

it('keeps voucher category codes immutable while allowing editable fields', function () {
    $category = VoucherCategory::query()->create([
        'code' => 'VC006',
        'name' => 'Custom',
        'status' => true,
        'sort_order' => 6,
    ]);

    $category->update([
        'code' => 'VC999',
        'name' => 'Custom Updated',
        'description' => 'Updated description',
    ]);

    expect($category->refresh()->code)->toBe('VC006')
        ->and($category->name)->toBe('Custom Updated')
        ->and($category->description)->toBe('Updated description');
});

it('generates sequential custom category codes after the configured system codes', function () {
    (new SystemVoucherCategorySeeder)->run();
    $service = app(VoucherCategoryService::class);

    $first = $service->create([
        'name' => 'Custom One',
        'description' => null,
        'status' => true,
        'sort_order' => 6,
    ]);
    $second = $service->create([
        'name' => 'Custom Two',
        'description' => null,
        'status' => true,
        'sort_order' => 7,
    ]);

    expect($first->code)->toBe('VC006')
        ->and($second->code)->toBe('VC007');
});

it('prevents deleting system and used voucher categories', function () {
    (new SystemVoucherCategorySeeder)->run();
    $service = app(VoucherCategoryService::class);
    $systemCategory = VoucherCategory::query()
        ->where('code', VoucherCategoryHelper::customerCode())
        ->firstOrFail();
    $usedCategory = $service->create([
        'name' => 'Used Custom Category',
        'description' => null,
        'status' => true,
        'sort_order' => 6,
    ]);
    DB::table('vouchers')->insert([
        'voucher_category_id' => $usedCategory->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $service->delete($systemCategory))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->delete($usedCategory))
        ->toThrow(ValidationException::class)
        ->and(VoucherCategory::query()->whereKey($usedCategory)->exists())
        ->toBeTrue();
});

it('identifies customer security deposit refunds by immutable category code', function () {
    $customerCategory = VoucherCategory::query()->create([
        'code' => VoucherCategoryHelper::customerCode(),
        'name' => 'Client Accounts',
        'status' => true,
        'sort_order' => 1,
        'is_system' => true,
    ]);
    $subType = PaymentSubType::query()->create([
        'code' => PaymentSubType::CUSTOMER_REFUND_GIVEN_CODE,
        'name' => 'Refund Given',
        'voucher_category_id' => $customerCategory->id,
        'type' => 'payment',
        'status' => true,
    ]);

    expect(app(CustomerSecurityDepositService::class)->isRefundSubType($subType))
        ->toBeTrue();
});
