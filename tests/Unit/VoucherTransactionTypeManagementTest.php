<?php

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\PaymentVoucherRequest;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use App\Services\VoucherTransactionTypeService;
use Database\Seeders\SystemVoucherCategorySeeder;
use Database\Seeders\SystemVoucherTransactionTypeSeeder;
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

    Schema::create('voucher_transaction_types', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_category_id');
        $table->string('code', 64);
        $table->string('name', 150);
        $table->string('voucher_type', 20);
        $table->string('report_bucket_code', 100)->nullable();
        $table->text('description')->nullable();
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
        $table->unique(['voucher_category_id', 'code']);
        $table->unique(['voucher_category_id', 'name']);
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

    Schema::create('vouchers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('voucher_transaction_type_id')->nullable();
        $table->timestamps();
    });

    (new SystemVoucherCategorySeeder)->run();
});

it('seeds configured system types idempotently with category scoped codes', function () {
    $seeder = new SystemVoucherTransactionTypeSeeder;
    $seeder->run();
    $seeder->run();

    $supplier = VoucherCategory::query()
        ->where('code', VoucherCategoryHelper::supplierCode())
        ->firstOrFail();
    $customer = VoucherCategory::query()
        ->where('code', VoucherCategoryHelper::customerCode())
        ->firstOrFail();

    expect(VoucherTransactionType::query()->count())
        ->toBe(count(VoucherTransactionTypeHelper::flattenedSystemTypes()))
        ->and(VoucherTransactionType::query()
            ->where('voucher_category_id', $supplier->id)
            ->where('code', '1019')
            ->exists())
        ->toBeTrue()
        ->and(VoucherTransactionType::query()
            ->where('voucher_category_id', $customer->id)
            ->where('code', '1019')
            ->exists())
        ->toBeTrue();
});

it('removes retired system types without breaking referenced vouchers', function () {
    (new SystemVoucherTransactionTypeSeeder)->run();
    $customer = VoucherCategory::query()
        ->where('code', VoucherCategoryHelper::customerCode())
        ->firstOrFail();

    $retiredType = VoucherTransactionType::query()->create([
        'voucher_category_id' => $customer->id,
        'code' => '1020',
        'name' => 'Security Deposit Receipt',
        'voucher_type' => VoucherTransactionTypeHelper::paymentVoucherType(),
        'sort_order' => 99,
        'status' => true,
        'is_system' => true,
    ]);

    (new SystemVoucherTransactionTypeSeeder)->run();

    expect(VoucherTransactionType::query()->find($retiredType->id))->toBeNull();

    $referencedType = VoucherTransactionType::query()->create([
        'voucher_category_id' => $customer->id,
        'code' => '1020',
        'name' => 'Security Deposit Receipt',
        'voucher_type' => VoucherTransactionTypeHelper::paymentVoucherType(),
        'sort_order' => 99,
        'status' => true,
        'is_system' => true,
    ]);
    DB::table('vouchers')->insert([
        'voucher_transaction_type_id' => $referencedType->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new SystemVoucherTransactionTypeSeeder)->run();
    $referencedType->refresh();

    expect($referencedType->status)->toBeFalse()
        ->and($referencedType->is_system)->toBeFalse();
});

it('preserves editable system names while keeping protected fields immutable', function () {
    (new SystemVoucherTransactionTypeSeeder)->run();
    $type = VoucherTransactionType::query()
        ->with('voucherCategory')
        ->where('code', VoucherTransactionTypeHelper::getCode(
            'employee',
            'monthly_salary'
        ))
        ->whereHas('voucherCategory', fn ($query) => $query
            ->where('code', VoucherCategoryHelper::employeeCode()))
        ->firstOrFail();

    $originalCategory = $type->voucher_category_id;
    $type->update([
        'name' => 'Monthly Payroll',
        'description' => 'Renamed by administrator',
        'sort_order' => 90,
        'status' => false,
        'code' => '9999',
        'voucher_type' => 'receipt',
        'voucher_category_id' => 999,
        'is_system' => false,
    ]);

    (new SystemVoucherTransactionTypeSeeder)->run();
    $type->refresh();

    expect($type->name)->toBe('Monthly Payroll')
        ->and($type->description)->toBe('Renamed by administrator')
        ->and($type->sort_order)->toBe(90)
        ->and($type->status)->toBeFalse()
        ->and($type->code)->toBe('1001')
        ->and($type->voucher_type)->toBe('payment')
        ->and($type->voucher_category_id)->toBe($originalCategory)
        ->and($type->is_system)->toBeTrue();
});

it('generates sequential custom codes and prevents deleting protected records', function () {
    (new SystemVoucherTransactionTypeSeeder)->run();
    $service = app(VoucherTransactionTypeService::class);
    $category = VoucherCategory::query()
        ->where('code', VoucherCategoryHelper::operatingCode())
        ->firstOrFail();

    $first = $service->create([
        'voucher_category_id' => $category->id,
        'name' => 'Custom Payment One',
        'voucher_type' => 'payment',
        'description' => null,
        'sort_order' => 100,
        'status' => true,
    ]);
    $second = $service->create([
        'voucher_category_id' => $category->id,
        'name' => 'Custom Payment Two',
        'voucher_type' => 'payment',
        'description' => null,
        'sort_order' => 101,
        'status' => true,
    ]);
    $systemType = VoucherTransactionType::query()
        ->where('is_system', true)
        ->firstOrFail();
    DB::table('vouchers')->insert([
        'voucher_transaction_type_id' => $first->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect((int) $second->code)->toBe((int) $first->code + 1)
        ->and(fn () => $service->delete($systemType))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->delete($first))
        ->toThrow(ValidationException::class);
});

it('normalizes the legacy payment subtype request field', function () {
    $request = PaymentVoucherRequest::create('/vouchers/payment', 'POST', [
        'date' => '2026-08-02',
        'vouchers' => [[
            'payment_sub_type_id' => 77,
        ]],
    ]);

    $method = new ReflectionMethod($request, 'prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('vouchers.0.voucher_transaction_type_id'))
        ->toBe(77);
});
