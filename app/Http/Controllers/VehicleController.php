<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehicleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-vehicle', only: ['index']),
            new Middleware('permission:create-vehicle', only: ['store']),
            new Middleware('permission:update-vehicle', only: ['update']),
            new Middleware('permission:delete-vehicle', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-vehicle-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $query = Vehicle::select('id', 'customer_id', 'vehicle_type', 'vehicle_name', 'vehicle_number', 'reg_date', 'status', 'created_at')
            ->with('customer:id,name', 'products:id,product_name');

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('vehicle_name', 'like', '%'.$request->search.'%')
                    ->orWhere('vehicle_number', 'like', '%'.$request->search.'%')
                    ->orWhere('vehicle_type', 'like', '%'.$request->search.'%')
                    ->orWhereHas('customer', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%'.$request->search.'%');
                    });
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer_id', $request->customer);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        // Apply sorting
        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'customer_id', 'vehicle_type', 'vehicle_name', 'vehicle_number', 'reg_date', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $vehicles = $query->paginate($perPage)->withQueryString()->through(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'vehicle_type' => $vehicle->vehicle_type,
                'vehicle_name' => $vehicle->vehicle_name,
                'vehicle_number' => $vehicle->vehicle_number,
                'reg_date' => $vehicle->reg_date?->format('Y-m-d'),
                'status' => $vehicle->status,
                'customer' => $vehicle->customer,
                'products' => $vehicle->products,
                'created_at' => $vehicle->created_at->format('Y-m-d'),
            ];
        });

        $customers = Customer::where('status', true)->get(['id', 'name']);
        $products = Product::where('status', 1)->get(['id', 'product_name as name']);

        return Inertia::render('Vehicles/Vehicles', [
            'vehicles' => $vehicles,
            'customers' => $customers,
            'products' => $products,
            'filters' => $request->only(['search', 'customer', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|distinct|exists:products,id',
            'vehicle_type' => 'nullable|string|max:150',
            'vehicle_name' => 'nullable|string|max:150',
            'vehicle_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'vehicle_number')
                    ->where(fn ($query) => $query->where('customer_id', $request->input('customer_id'))),
            ],
            'reg_date' => 'nullable|date',
            'status' => 'boolean',
        ]);

        DB::transaction(function () use ($validated): void {
            $vehicle = Vehicle::create([
                'customer_id' => $validated['customer_id'],
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_name' => $validated['vehicle_name'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'reg_date' => $validated['reg_date'] ?? null,
                'status' => $validated['status'] ?? true,
            ]);

            $vehicle->products()->sync($validated['product_ids'] ?? []);
        });

        return redirect()->back()->with('success', 'Vehicle created successfully.');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|distinct|exists:products,id',
            'vehicle_type' => 'nullable|string|max:150',
            'vehicle_name' => 'nullable|string|max:150',
            'vehicle_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'vehicle_number')
                    ->where(fn ($query) => $query->where('customer_id', $request->input('customer_id')))
                    ->ignore($vehicle->id),
            ],
            'reg_date' => 'nullable|date',
            'status' => 'boolean',
        ]);

        DB::transaction(function () use ($vehicle, $validated): void {
            $vehicle->update([
                'customer_id' => $validated['customer_id'],
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_name' => $validated['vehicle_name'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'reg_date' => $validated['reg_date'] ?? null,
                'status' => $validated['status'] ?? true,
            ]);

            $vehicle->products()->sync($validated['product_ids'] ?? []);
        });

        return redirect()->back()->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return redirect()->back()->with('success', 'Vehicle deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:vehicles,id',
        ]);

        Vehicle::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids).' vehicles deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Vehicle::select('id', 'customer_id', 'vehicle_type', 'vehicle_name', 'vehicle_number', 'reg_date', 'status', 'created_at')
            ->with('customer:id,name', 'products:id,product_name');

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('vehicle_name', 'like', '%'.$request->search.'%')
                    ->orWhere('vehicle_number', 'like', '%'.$request->search.'%')
                    ->orWhere('vehicle_type', 'like', '%'.$request->search.'%')
                    ->orWhereHas('customer', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%'.$request->search.'%');
                    });
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer_id', $request->customer);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'customer_id', 'vehicle_type', 'vehicle_name', 'vehicle_number', 'reg_date', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $vehicles = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.vehicles', compact('vehicles', 'companySetting'));

        return $pdf->stream('vehicles.pdf');
    }
}
