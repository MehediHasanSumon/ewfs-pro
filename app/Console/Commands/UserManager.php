<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserManager extends Command
{
    protected $signature = 'user:manage 
                            {action? : Action to perform (list|create|assign|permissions|super-admin)}
                            {--user= : User ID or email}
                            {--role= : Role name}
                            {--permission= : Permission name}';

    protected $description = 'Advanced user management: list users, assign roles/permissions, create super admin';

    public function handle()
    {
        $action = $this->argument('action');

        if (!$action) {
            $action = $this->choice('What would you like to do?', [
                'list' => 'List all users',
                'create' => 'Create new user',
                'assign' => 'Assign role to user',
                'permissions' => 'Manage user permissions',
                'super-admin' => 'Create super admin',
                'roles' => 'List all roles',
                'create-role' => 'Create new role',
                'create-permission' => 'Create new permission',
                'setup' => 'Initial setup (roles & permissions)'
            ]);
        }

        switch ($action) {
            case 'list':
                $this->listUsers();
                break;
            case 'create':
                $this->createUser();
                break;
            case 'assign':
                $this->assignRole();
                break;
            case 'permissions':
                $this->managePermissions();
                break;
            case 'super-admin':
                $this->createSuperAdmin();
                break;
            case 'roles':
                $this->listRoles();
                break;
            case 'create-role':
                $this->createRole();
                break;
            case 'create-permission':
                $this->createPermission();
                break;
            case 'setup':
                $this->initialSetup();
                break;
            default:
                $this->error('Invalid action');
        }
    }

    private function listUsers()
    {
        $users = User::with('roles', 'permissions')->get();

        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return;
        }

        $this->info('📋 All Users:');
        $this->line('');

        $headers = ['ID', 'Name', 'Email', 'Status', 'Roles', 'Direct Permissions'];
        $rows = [];

        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->join(', ') ?: 'None';
            $permissions = $user->permissions->pluck('name')->join(', ') ?: 'None';

            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->status ? '✅ Active' : '❌ Inactive',
                $roles,
                $permissions
            ];
        }

        $this->table($headers, $rows);
    }

    private function createUser()
    {
        $this->info('🆕 Creating New User');

        $name = $this->ask('Enter user name');
        $email = $this->ask('Enter user email');
        $password = $this->secret('Enter password (min 8 characters)');
        $confirmPassword = $this->secret('Confirm password');

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match!');
            return;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'status' => true,
        ]);

        $this->info("✅ User created successfully: {$user->email}");

        if ($this->confirm('Would you like to assign a role to this user?')) {
            $this->assignRoleToUser($user);
        }
    }

    private function assignRole()
    {
        $userIdentifier = $this->option('user') ?: $this->ask('Enter user ID or email');
        $user = $this->findUser($userIdentifier);

        if (!$user) return;

        $this->assignRoleToUser($user);
    }

    private function assignRoleToUser($user)
    {
        $roles = Role::all()->pluck('name')->toArray();

        if (empty($roles)) {
            $this->warn('No roles found. Run setup first.');
            return;
        }

        $this->info("Current roles for {$user->name}: " . $user->roles->pluck('name')->join(', '));

        $selectedRole = $this->choice('Select role to assign', $roles);

        if ($user->hasRole($selectedRole)) {
            $this->warn("User already has the '{$selectedRole}' role.");
            return;
        }

        $user->assignRole($selectedRole);
        $this->info("✅ Role '{$selectedRole}' assigned to {$user->name}");
    }

    private function managePermissions()
    {
        $userIdentifier = $this->option('user') ?: $this->ask('Enter user ID or email');
        $user = $this->findUser($userIdentifier);

        if (!$user) return;

        $action = $this->choice('What would you like to do?', [
            'view' => 'View user permissions',
            'assign' => 'Assign permission',
            'revoke' => 'Revoke permission'
        ]);

        switch ($action) {
            case 'view':
                $this->viewUserPermissions($user);
                break;
            case 'assign':
                $this->assignPermissionToUser($user);
                break;
            case 'revoke':
                $this->revokePermissionFromUser($user);
                break;
        }
    }

    private function viewUserPermissions($user)
    {
        $this->info("📋 Permissions for {$user->name}:");

        $rolePermissions = $user->getPermissionsViaRoles();
        $directPermissions = $user->getDirectPermissions();

        $this->line('');
        $this->info('Via Roles:');
        if ($rolePermissions->isEmpty()) {
            $this->line('  None');
        } else {
            foreach ($rolePermissions as $permission) {
                $this->line("  • {$permission->name}");
            }
        }

        $this->line('');
        $this->info('Direct Permissions:');
        if ($directPermissions->isEmpty()) {
            $this->line('  None');
        } else {
            foreach ($directPermissions as $permission) {
                $this->line("  • {$permission->name}");
            }
        }
    }

    private function assignPermissionToUser($user)
    {
        $permissions = Permission::all()->pluck('name')->toArray();

        if (empty($permissions)) {
            $this->warn('No permissions found. Run setup first.');
            return;
        }

        $selectedPermission = $this->choice('Select permission to assign', $permissions);

        if ($user->hasPermissionTo($selectedPermission)) {
            $this->warn("User already has the '{$selectedPermission}' permission.");
            return;
        }

        $user->givePermissionTo($selectedPermission);
        $this->info("✅ Permission '{$selectedPermission}' assigned to {$user->name}");
    }

    private function revokePermissionFromUser($user)
    {
        $userPermissions = $user->getDirectPermissions()->pluck('name')->toArray();

        if (empty($userPermissions)) {
            $this->warn('User has no direct permissions to revoke.');
            return;
        }

        $selectedPermission = $this->choice('Select permission to revoke', $userPermissions);

        $user->revokePermissionTo($selectedPermission);
        $this->info("✅ Permission '{$selectedPermission}' revoked from {$user->name}");
    }

    private function createSuperAdmin()
    {
        $this->info('👑 Creating Super Admin');

        $name = $this->ask('Enter super admin name');
        $email = $this->ask('Enter super admin email');
        $password = $this->secret('Enter password (min 8 characters)');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => true,
            ]);

            // Ensure super-admin role exists
            $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

            // Assign all permissions to super-admin role
            $superAdminRole->syncPermissions(Permission::all());

            // Assign super-admin role to user
            $user->assignRole('super-admin');

            DB::commit();

            $this->info("✅ Super Admin created successfully: {$user->email}");
            $this->info("👑 User has been granted all permissions via super-admin role");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to create super admin: {$e->getMessage()}");
        }
    }

    private function listRoles()
    {
        $roles = Role::with('permissions')->get();

        if ($roles->isEmpty()) {
            $this->warn('No roles found.');
            return;
        }

        $this->info('📋 All Roles:');
        $this->line('');

        foreach ($roles as $role) {
            $this->info("🔑 {$role->name}");
            $permissions = $role->permissions->pluck('name');

            if ($permissions->isEmpty()) {
                $this->line('  No permissions assigned');
            } else {
                foreach ($permissions as $permission) {
                    $this->line("  • {$permission}");
                }
            }
            $this->line('');
        }
    }

    private function createRole()
    {
        $this->info('🔑 Creating New Role');

        $roleName = $this->ask('Enter role name');

        if (Role::where('name', $roleName)->exists()) {
            $this->error('Role already exists!');
            return;
        }

        $role = Role::create(['name' => $roleName]);
        $this->info("✅ Role '{$roleName}' created successfully");

        if ($this->confirm('Would you like to assign permissions to this role?')) {
            $this->assignPermissionsToRole($role);
        }
    }

    private function createPermission()
    {
        $this->info('⚡ Creating New Permission');

        $permissionName = $this->ask('Enter permission name');

        if (Permission::where('name', $permissionName)->exists()) {
            $this->error('Permission already exists!');
            return;
        }

        Permission::create(['name' => $permissionName]);
        $this->info("✅ Permission '{$permissionName}' created successfully");
    }

    private function assignPermissionsToRole($role)
    {
        $permissions = Permission::all()->pluck('name')->toArray();

        if (empty($permissions)) {
            $this->warn('No permissions found.');
            return;
        }

        $selectedPermissions = $this->choice(
            'Select permissions to assign (separate multiple with comma)',
            array_merge(['all'], $permissions),
            null,
            null,
            true
        );

        if (in_array('all', $selectedPermissions)) {
            $role->syncPermissions(Permission::all());
            $this->info('✅ All permissions assigned to role');
        } else {
            $role->syncPermissions($selectedPermissions);
            $this->info('✅ Selected permissions assigned to role');
        }
    }

    private function initialSetup()
    {
        $this->info('🚀 Running Initial Setup...');

        DB::beginTransaction();

        try {
            // Create roles
            $roles = ['super-admin', 'admin', 'user'];
            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName]);
            }
            $this->info('✅ Roles created: ' . implode(', ', $roles));

            // Dynamically get model names from app/Models
            $modelPath = app_path('Models');
            $models = [];

            if (File::isDirectory($modelPath)) {
                foreach (File::files($modelPath) as $file) {
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    $models[] = Str::kebab($filename);
                }
            }

            $this->info('Models found: ' . implode(', ', $models));

            // Create permissions for each model
            $actions = ['create', 'update', 'view', 'delete'];

            foreach ($models as $model) {
                foreach ($actions as $action) {
                    Permission::firstOrCreate(['name' => "{$action}-{$model}"]);
                }
                Permission::firstOrCreate(['name' => "can-{$model}-filter"]);
                Permission::firstOrCreate(['name' => "can-{$model}-download"]);
            }

            $extraPermissions = [
                'create-role',
                'update-role',
                'delete-role',
                'view-role',
                'can-role-filter',
                'can-role-download',
                'create-permission',
                'update-permission',
                'delete-permission',
                'view-permission',
                'can-permission-filter',
                'can-permission-download',
                'view-dashboard',
                'view-loan',
                'view-shift-closed-list',
                'create-s-m-s-template',
                'update-s-m-s-template',
                'delete-s-m-s-template',
                'view-s-m-s-template',
                'can-s-m-s-template-filter',
                'can-s-m-s-template-download'
            ];

            foreach ($extraPermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
            }

            $this->info('All permissions (dynamic and extra) created.');

            // Assign all permissions to super-admin
            $superAdminRole = Role::where('name', 'super-admin')->first();
            $superAdminRole->syncPermissions(Permission::all());

            $this->info('✅ All permissions assigned to super-admin role');

            DB::commit();

            $this->info('🎉 Initial setup completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Setup failed: {$e->getMessage()}");
        }
    }

    private function findUser($identifier)
    {
        $user = is_numeric($identifier)
            ? User::find($identifier)
            : User::where('email', $identifier)->first();

        if (!$user) {
            $this->error('User not found!');
            return null;
        }

        return $user;
    }
}
