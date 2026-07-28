<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpDepartmentRequest;
use App\Models\CompanySetting;
use App\Models\EmpDepartment;
use App\Models\EmpType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class EmpDepartmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-employee|can-emp-department-download', only: ['index', 'downloadPdf']),
            new Middleware('permission:create-employee', only: ['store']),
            new Middleware('permission:update-employee', only: ['update']),
            new Middleware('permission:delete-employee', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $departments = $this->filteredQuery($request)
            ->with('empType:id,name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmpDepartment $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'emp_type_id' => $department->emp_type_id,
                'emp_type_name' => $department->empType?->name,
                'status' => $department->status,
                'created_at' => $department->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('EmpDepartments/EmpDepartments', [
            'departments' => $departments,
            'empTypes' => EmpType::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only([
                'search',
                'status',
                'emp_type_id',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(EmpDepartmentRequest $request)
    {
        EmpDepartment::create($request->validated());

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(EmpDepartmentRequest $request, EmpDepartment $empDepartment)
    {
        $empDepartment->update($request->validated());

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(EmpDepartment $empDepartment)
    {
        $empDepartment->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:emp_departments,id'],
        ]);

        $deleted = EmpDepartment::query()->whereKey($validated['ids'])->delete();

        return redirect()->back()->with('success', "{$deleted} departments deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $departments = $this->filteredQuery($request)->with('empType')->get();
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.emp-departments', compact('departments', 'companySetting'));

        return $pdf->stream('emp-departments.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = EmpDepartment::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('emp_type_id') && $request->input('emp_type_id') !== 'all') {
            $query->where('emp_type_id', $request->integer('emp_type_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        $allowedSorts = ['id', 'name', 'emp_type_id', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
