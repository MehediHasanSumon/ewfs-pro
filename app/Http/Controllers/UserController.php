<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-user', only: ['index']),
            new Middleware('permission:create-user', only: ['store']),
            new Middleware('permission:update-user', only: ['edit', 'update']),
            new Middleware('permission:delete-user', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-user-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'email_verified_at', 'banned', 'created_at')
            ->with('roles:name');

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->role && $request->role !== 'all') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            } elseif ($request->status === 'banned') {
                $query->where('banned', true);
            } elseif ($request->status === 'active') {
                $query->where('banned', false);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply sorting
        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'name', 'email', 'email_verified_at', 'banned', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $users = $query->paginate($perPage)->withQueryString()->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'email_verified' => ! is_null($user->email_verified_at),
                'banned' => $user->banned,
                'created_at' => $user->created_at->format('Y-m-d'),
            ];
        });

        $roles = Role::all(['id', 'name']);

        return Inertia::render('Users/Users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'email_verified' => 'boolean',
            'banned' => 'boolean',
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => ($validated['email_verified'] ?? false) ? now() : null,
                'banned' => $validated['banned'] ?? false,
            ]);

            $user->syncRoles($validated['roles'] ?? []);
        });

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return response()->json([
            'userRoles' => $user->roles->pluck('id')->toArray(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|min:8',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'email_verified' => 'boolean',
            'banned' => 'boolean',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => ($validated['email_verified'] ?? false) ? now() : null,
            'banned' => $validated['banned'] ?? false,
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($user, $userData, $validated): void {
            $user->update($userData);
            $user->syncRoles($validated['roles'] ?? []);
        });

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
        ]);

        User::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids).' users deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'email_verified_at', 'banned', 'created_at')
            ->with('roles:name');

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->role && $request->role !== 'all') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            } elseif ($request->status === 'banned') {
                $query->where('banned', true);
            } elseif ($request->status === 'active') {
                $query->where('banned', false);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'name', 'email', 'email_verified_at', 'banned', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.users', compact('users', 'companySetting'));

        return $pdf->stream('users.pdf');
    }
}
