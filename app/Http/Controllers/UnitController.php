<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class UnitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-unit', only: ['index']),
            new Middleware('permission:create-unit', only: ['store']),
            new Middleware('permission:update-unit', only: ['update']),
            new Middleware('permission:delete-unit', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-unit-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $query = Unit::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('value', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
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
        $units = $query->paginate($perPage)->withQueryString()->through(function ($unit) {
            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'value' => $unit->value,
                'status' => (bool) $unit->status,
                'created_at' => $unit->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('Units/Units', [
            'units' => $units,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        Unit::create([
            'name' => $request->name,
            'value' => $request->value,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $unit->update([
            'name' => $request->name,
            'value' => $request->value,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return redirect()->back()->with('success', 'Unit deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:units,id'
        ]);

        Unit::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' units deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Unit::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('value', 'like', '%' . $request->search . '%');
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

        $units = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.units', compact('units', 'companySetting'));
        return $pdf->stream('units.pdf');
    }
}