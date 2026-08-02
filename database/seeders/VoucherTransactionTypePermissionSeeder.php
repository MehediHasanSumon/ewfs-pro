<?php

namespace Database\Seeders;

use App\Helpers\VoucherTransactionTypeHelper;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class VoucherTransactionTypePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(VoucherTransactionTypeHelper::permissionNames())
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
