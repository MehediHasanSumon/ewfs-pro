<?php

namespace App\Http\Controllers;

use App\Models\EmpType;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class EmpTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-employee|can-emp-type-download', only: ['index', 'downloadPdf']),
            new Middleware('permission:create-employee', only: ['store']),
            new Middleware('permission:update-employee', only: ['update']),
            new Middleware('permission:delete-employee', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = EmpType::query();

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
        $empTypes = $query->paginate($perPage)->withQueryString()->through(function ($empType) {
            return [
                'id' => $empType->id,
                'name' => $empType->name,
                'status' => (bool) $empType->status,
                'created_at' => $empType->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('EmpTypes/EmpTypes', [
            'empTypes' => $empTypes,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        EmpType::create([
            'name' => $request->name,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Employee type created successfully.');
    }

    public function update(Request $request, EmpType $empType)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $empType->update([
            'name' => $request->name,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Employee type updated successfully.');
    }

    public function destroy(EmpType $empType)
    {
        $empType->delete();
        return redirect()->back()->with('success', 'Employee type deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:emp_types,id'
        ]);

        EmpType::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' employee types deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = EmpType::query();

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

        $empTypes = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.emp-types', compact('empTypes', 'companySetting'));
        return $pdf->stream('emp-types.pdf');
    }
}