<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-permission', only: ['index']),
            new Middleware('permission:can-permission-download', only: ['downloadPdf']),
            new Middleware('permission:create-permission', only: ['store']),
            new Middleware('permission:update-permission', only: ['update']),
            new Middleware('permission:delete-permission', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Permission::withCount('roles');

        // Apply filters
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        if ($request->module && $request->module !== 'all') {
            $query->where('name', 'like', '%' . $request->module . '%');
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
        $permissions = $query->paginate($perPage)->withQueryString()->through(function ($permission) {
            $parts = explode(' ', $permission->name);
            $module = count($parts) > 1 ? ucfirst($parts[1]) : 'General';
            
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => 'Permission to ' . $permission->name,
                'module' => $module,
                'roles_count' => $permission->roles_count,
                'created_at' => $permission->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('Permissions/Permissions', [
            'permissions' => $permissions,
            'filters' => $request->only(['search', 'module', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'guard_name' => 'string'
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web'
        ]);

        return redirect()->back()->with('success', 'Permission created successfully.');
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
            'guard_name' => 'string'
        ]);

        $permission->update([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web'
        ]);

        return redirect()->back()->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return redirect()->back()->with('success', 'Permission deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:permissions,id'
        ]);

        Permission::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' permissions deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Permission::withCount('roles');

        // Apply same filters as index method
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        if ($request->module && $request->module !== 'all') {
            $query->where('name', 'like', '%' . $request->module . '%');
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

        $permissions = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.permissions', compact('permissions', 'companySetting'));
        return $pdf->stream('permissions.pdf');
    }
}