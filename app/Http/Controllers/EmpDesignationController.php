<?php

namespace App\Http\Controllers;

use App\Models\EmpDesignation;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $query = EmpDesignation::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true');
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
        $designations = $query->paginate($perPage)->withQueryString()->through(function ($designation) {
            return [
                'id' => $designation->id,
                'name' => $designation->name,
                'status' => (bool) $designation->status,
                'created_at' => $designation->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('EmpDesignations/EmpDesignations', [
            'designations' => $designations,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        EmpDesignation::create([
            'name' => $request->name,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Designation created successfully.');
    }

    public function update(Request $request, EmpDesignation $empDesignation)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $empDesignation->update([
            'name' => $request->name,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Designation updated successfully.');
    }

    public function destroy(EmpDesignation $empDesignation)
    {
        $empDesignation->delete();
        return redirect()->back()->with('success', 'Designation deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:emp_designations,id'
        ]);

        EmpDesignation::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' designations deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = EmpDesignation::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true');
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

        $designations = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.emp-designations', compact('designations', 'companySetting'));
        return $pdf->stream('emp-designations.pdf');
    }
}