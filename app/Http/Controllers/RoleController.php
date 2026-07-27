<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-role', only: ['index']),
            new Middleware('permission:can-role-download', only: ['downloadPdf']),
            new Middleware('permission:create-role', only: ['store']),
            new Middleware('permission:update-role', only: ['edit', 'update']),
            new Middleware('permission:delete-role', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Role::withCount(['permissions', 'users']);

        // Apply filters
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 10);
        $roles = $query->paginate($perPage)->withQueryString()->through(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'description' => 'Role for ' . $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'created_at' => $role->created_at->format('Y-m-d'),
            ];
        });

        $permissions = Permission::all(['id', 'name']);

        return Inertia::render('Roles/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web'
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->back()->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->update([
            'name' => $request->name
        ]);

        $role->syncPermissions($request->permissions ?? []);

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function edit(Role $role)
    {
        return response()->json([
            'rolePermissions' => $role->permissions->pluck('id')->toArray()
        ]);
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete role with assigned users.']);
        }

        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:roles,id'
        ]);

        $rolesWithUsers = Role::whereIn('id', $request->ids)
            ->whereHas('users')
            ->count();

        if ($rolesWithUsers > 0) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete roles with assigned users.']);
        }

        Role::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' roles deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Role::withCount(['permissions', 'users']);

        // Apply same filters as index method
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $roles = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.roles', compact('roles', 'companySetting'));
        return $pdf->stream('roles.pdf');
    }
}