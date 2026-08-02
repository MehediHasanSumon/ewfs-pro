<?php

namespace Database\Seeders;

use App\Helpers\VoucherCategoryHelper;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class VoucherCategoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(VoucherCategoryHelper::permissionNames())
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        $superAdmin = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->first();

        if ($superAdmin) {
            $permissions->each(
                fn (Permission $permission) => $superAdmin->givePermissionTo($permission)
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
