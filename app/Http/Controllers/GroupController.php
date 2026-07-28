<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Group;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account|can-group-download', only: ['index', 'getParentChild', 'downloadPdf']),
            new Middleware('permission:create-account', only: ['store']),
            new Middleware('permission:update-account', only: ['update']),
            new Middleware('permission:delete-account', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function getParentChild($code)
    {
        $parentChild = Group::where('code', 'like', $code.'%')
            ->pluck('name', 'code')
            ->toArray();

        return response()->json($parentChild);
    }

    public function index(Request $request)
    {
        $query = $this->groupListingQuery();

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('groups.name', 'like', '%'.$request->search.'%')
                    ->orWhere('groups.code', 'like', '%'.$request->search.'%')
                    ->orWhere('f2.name', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->master_group && $request->master_group !== 'all') {
            $query->where('groups.code', 'like', $request->master_group.'%');
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('groups.status', $request->status);
        }

        // Apply sorting
        $sortColumns = [
            'id' => 'groups.id',
            'name' => 'groups.name',
            'code' => 'groups.code',
            'parent_name' => 'f2.name',
            'status' => 'groups.status',
            'created_at' => 'groups.created_at',
            'groups.created_at' => 'groups.created_at',
        ];
        $sortBy = $sortColumns[$request->get('sort_by')] ?? 'groups.created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $finances = $query->paginate($perPage)->withQueryString();

        $financeMasterGroup = Group::query()
            ->whereNull('parent_id')
            ->pluck('name', 'code')
            ->all();

        return Inertia::render('Groups/Groups', [
            'groups' => $finances,
            'masterGroups' => $financeMasterGroup,
            'filters' => $request->only(['search', 'master_group', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'exists:groups,code'],
            'parents' => ['nullable', 'string', 'exists:groups,code'],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $parentCode = $validated['parents'] ?: $validated['code'];
            $parent = Group::query()
                ->where('code', $parentCode)
                ->lockForUpdate()
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parents' => 'The selected parent group is invalid.',
                ]);
            }

            $name = strip_tags($validated['name']);
            $duplicateName = Group::query()
                ->where('parent_id', $parent->id)
                ->where('name', $name)
                ->exists();

            if ($duplicateName) {
                throw ValidationException::withMessages([
                    'name' => 'The group name has already been taken under this parent.',
                ]);
            }

            $lastChildCode = Group::query()
                ->where('parent_id', $parent->id)
                ->orderByDesc('code')
                ->value('code');
            $nextSequence = $lastChildCode
                ? ((int) substr($lastChildCode, strlen($parent->code))) + 1
                : 1;

            if ($nextSequence > 9999) {
                throw ValidationException::withMessages([
                    'parents' => 'The selected parent group has reached its child group limit.',
                ]);
            }

            Group::create([
                'parent_id' => $parent->id,
                'code' => $parent->code.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'account_class' => $parent->account_class,
                'normal_balance' => $parent->normal_balance,
                'is_system' => false,
                'status' => true,
            ]);
        });

        return redirect()->back()->with('success', 'Group created successfully.');
    }

    public function update(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $group->update([
            'name' => strip_tags($request->name),
        ]);

        return redirect()->back()->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect()->back()->with('success', 'Group deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Group::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', count($ids).' groups deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = $this->groupListingQuery();

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('groups.name', 'like', '%'.$request->search.'%')
                    ->orWhere('groups.code', 'like', '%'.$request->search.'%')
                    ->orWhere('f2.name', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->master_group && $request->master_group !== 'all') {
            $query->where('groups.code', 'like', $request->master_group.'%');
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('groups.status', $request->status);
        }

        // Apply sorting
        $sortColumns = [
            'id' => 'groups.id',
            'name' => 'groups.name',
            'code' => 'groups.code',
            'parent_name' => 'f2.name',
            'status' => 'groups.status',
            'created_at' => 'groups.created_at',
            'groups.created_at' => 'groups.created_at',
        ];
        $sortBy = $sortColumns[$request->get('sort_by')] ?? 'groups.created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $groups = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.groups', compact('groups', 'companySetting'));

        return $pdf->stream('groups.pdf');
    }

    private function groupListingQuery()
    {
        return Group::query()
            ->select(
                'groups.*',
                'f2.name as parent_name',
                'f2.code as parents',
            )
            ->leftJoin('groups as f2', 'f2.id', '=', 'groups.parent_id')
            ->whereNotNull('groups.parent_id');
    }
}
