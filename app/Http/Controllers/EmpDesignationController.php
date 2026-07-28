<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpDesignationRequest;
use App\Models\CompanySetting;
use App\Models\EmpDesignation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class EmpDesignationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-employee|can-emp-designation-download', only: ['index', 'downloadPdf']),
            new Middleware('permission:create-employee', only: ['store']),
            new Middleware('permission:update-employee', only: ['update']),
            new Middleware('permission:delete-employee', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $designations = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmpDesignation $designation) => [
                'id' => $designation->id,
                'name' => $designation->name,
                'status' => $designation->status,
                'created_at' => $designation->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('EmpDesignations/EmpDesignations', [
            'designations' => $designations,
            'filters' => $request->only([
                'search',
                'status',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(EmpDesignationRequest $request)
    {
        EmpDesignation::create($request->validated());

        return redirect()->back()->with('success', 'Designation created successfully.');
    }

    public function update(EmpDesignationRequest $request, EmpDesignation $empDesignation)
    {
        $empDesignation->update($request->validated());

        return redirect()->back()->with('success', 'Designation updated successfully.');
    }

    public function destroy(EmpDesignation $empDesignation)
    {
        $empDesignation->delete();

        return redirect()->back()->with('success', 'Designation deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:emp_designations,id'],
        ]);

        $deleted = EmpDesignation::query()->whereKey($validated['ids'])->delete();

        return redirect()->back()->with('success', "{$deleted} designations deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $designations = $this->filteredQuery($request)->get();
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.emp-designations', compact('designations', 'companySetting'));

        return $pdf->stream('emp-designations.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = EmpDesignation::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
