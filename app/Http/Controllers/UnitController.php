<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\CompanySetting;
use App\Models\Unit;
use App\Services\CatalogReferenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class UnitController extends Controller implements HasMiddleware
{
    public function __construct(private readonly CatalogReferenceService $catalogReferenceService) {}

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
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $units = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'value' => $unit->value,
                'status' => $unit->status,
                'created_at' => $unit->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Units/Units', [
            'units' => $units,
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

    public function store(UnitRequest $request)
    {
        Unit::create($request->validated());

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    public function update(UnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());

        return redirect()->back()->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        $this->catalogReferenceService->deleteUnit($unit);

        return redirect()->back()->with('success', 'Unit deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:units,id'],
        ]);

        $deleted = $this->catalogReferenceService->deleteManyUnits($validated['ids']);

        return redirect()->back()->with('success', "{$deleted} units deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $units = $this->filteredQuery($request)->get();
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.units', compact('units', 'companySetting'));

        return $pdf->stream('units.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Unit::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
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

        $allowedSorts = ['id', 'name', 'value', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
