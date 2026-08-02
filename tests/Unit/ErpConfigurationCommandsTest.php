<?php

use App\Helpers\ErpHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Models\Category;
use App\Models\VoucherCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
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

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->string('module', 100)->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->text('description')->nullable();
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('synchronizes config-backed ERP master data', function () {
    $this->artisan('app:config')
        ->expectsOutputToContain('ERP constant master data synchronized successfully.')
        ->assertSuccessful();

    expect(
        Category::query()
            ->whereIn('code', ErpHelper::getReservedCategoryCodes())
            ->count()
    )->toBe(count(ErpHelper::getReservedCategoryCodes()))
        ->and(
            VoucherCategory::query()
                ->whereIn('code', VoucherCategoryHelper::getSystemCategoryCodes())
                ->where('is_system', true)
                ->count()
        )->toBe(count(VoucherCategoryHelper::getSystemCategoryCodes()));
});

it('creates voucher category permissions through user manager setup', function () {
    $this->artisan('user:manage', ['action' => 'setup'])
        ->assertSuccessful();

    $permissions = Permission::query()
        ->whereIn('name', VoucherCategoryHelper::permissionNames())
        ->pluck('name')
        ->all();

    $superAdmin = Role::query()->where('name', 'super-admin')->firstOrFail();

    expect($permissions)
        ->toHaveCount(count(VoucherCategoryHelper::permissionNames()))
        ->and($superAdmin->hasAllPermissions(VoucherCategoryHelper::permissionNames()))
        ->toBeTrue();
});
