<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $parentChild = Group::where('code', 'like', $code . '%')
            ->pluck('name', 'code')
            ->toArray();
        return response()->json($parentChild);
    }

    public function index(Request $request)
    {
        $query = Group::select('groups.*', 'f2.name as parent_name')
            ->leftJoin('groups as f2', 'f2.code', '=', 'groups.parents')
            ->where('groups.parents', '!=', 'ROOT');

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('groups.name', 'like', '%' . $request->search . '%')
                    ->orWhere('groups.code', 'like', '%' . $request->search . '%')
                    ->orWhere('f2.name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->master_group && $request->master_group !== 'all') {
            $query->where('groups.code', 'like', $request->master_group . '%');
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('groups.status', $request->status);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'groups.created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 10);
        $finances = $query->paginate($perPage)->withQueryString();

        $financeMasterGroup = Group::where('parents', 'ROOT')
            ->pluck('name', 'code')
            ->all();

        return Inertia::render('Groups/Groups', [
            'groups' => $finances,
            'masterGroups' => $financeMasterGroup,
            'filters' => $request->only(['search', 'master_group', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:groups,name',
            ],
        ], [
            'name.unique' => 'The group name has already been taken.',
        ]);

        $groupParents = $request->parents ?: 'ROOT';

        // Find the next available code
        $count = 1;
        do {
            $new_code = $groupParents . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = Group::where('code', $new_code)->exists();
            $count++;
        } while ($exists);

        Group::create([
            'code' => $new_code,
            'name' => strip_tags($request->name),
            'parents' => $groupParents,
            'status' => 1,
        ]);

        return redirect()->back()->with('success', 'Group created successfully.');
    }

    public function update(Request $request, Group $Group)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $Group->update([
            'name' => strip_tags($request->name),
        ]);

        return redirect()->back()->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $Group)
    {
        $Group->delete();
        return redirect()->back()->with('success', 'Group deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Group::whereIn('id', $ids)->delete();
        return redirect()->back()->with('success', count($ids) . ' groups deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Group::select('groups.*', 'f2.name as parent_name')
            ->leftJoin('groups as f2', 'f2.code', '=', 'groups.parents')
            ->where('groups.parents', '!=', 'ROOT');

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('groups.name', 'like', '%' . $request->search . '%')
                    ->orWhere('groups.code', 'like', '%' . $request->search . '%')
                    ->orWhere('f2.name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->master_group && $request->master_group !== 'all') {
            $query->where('groups.code', 'like', $request->master_group . '%');
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('groups.status', $request->status);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'groups.created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $groups = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.groups', compact('groups', 'companySetting'));
        return $pdf->stream('groups.pdf');
    }
}
