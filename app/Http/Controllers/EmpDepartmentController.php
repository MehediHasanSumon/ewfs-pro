<?php

namespace App\Http\Controllers;

use App\Models\EmpDepartment;
use App\Models\EmpType;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $query = EmpDepartment::with('empType');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true');
        }

        if ($request->filled('emp_type_id') && $request->emp_type_id !== 'all') {
            $query->where('emp_type_id', $request->emp_type_id);
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

        $perPage = $request->get('per_page', 10);
        $departments = $query->paginate($perPage)->withQueryString()->through(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'emp_type_id' => $department->emp_type_id,
                'emp_type_name' => $department->empType?->name,
                'status' => (bool) $department->status,
                'created_at' => $department->created_at->format('Y-m-d'),
            ];
        });

        $empTypes = EmpType::where('status', true)->get(['id', 'name']);

        return Inertia::render('EmpDepartments/EmpDepartments', [
            'departments' => $departments,
            'empTypes' => $empTypes,
            'filters' => $request->only(['search', 'status', 'emp_type_id', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'emp_type_id' => 'nullable|exists:emp_types,id',
            'status' => 'boolean'
        ]);

        EmpDepartment::create([
            'name' => $request->name,
            'emp_type_id' => $request->emp_type_id,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, EmpDepartment $empDepartment)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'emp_type_id' => 'nullable|exists:emp_types,id',
            'status' => 'boolean'
        ]);

        $empDepartment->update([
            'name' => $request->name,
            'emp_type_id' => $request->emp_type_id,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(EmpDepartment $empDepartment)
    {
        $empDepartment->delete();
        return redirect()->back()->with('success', 'Department deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:emp_departments,id'
        ]);

        EmpDepartment::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' departments deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = EmpDepartment::with('empType');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true');
        }

        if ($request->filled('emp_type_id') && $request->emp_type_id !== 'all') {
            $query->where('emp_type_id', $request->emp_type_id);
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

        $departments = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.emp-departments', compact('departments', 'companySetting'));
        return $pdf->stream('emp-departments.pdf');
    }
}