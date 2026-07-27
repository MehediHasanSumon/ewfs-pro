<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

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
                $q->where('vehicle_name', 'like', '%' . $request->search . '%')
                    ->orWhere('vehicle_number', 'like', '%' . $request->search . '%')
                    ->orWhere('vehicle_type', 'like', '%' . $request->search . '%')
                    ->orWhereHas('customer', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
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
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 10);
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
            'filters' => $request->only(['search', 'customer', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'vehicle_type' => 'nullable|string|max:150',
            'vehicle_name' => 'nullable|string|max:150',
            'vehicle_number' => 'nullable|string|max:50',
            'reg_date' => 'nullable|date',
            'status' => 'boolean'
        ]);

        $vehicle = Vehicle::create([
            'customer_id' => $request->customer_id,
            'vehicle_type' => $request->vehicle_type,
            'vehicle_name' => $request->vehicle_name,
            'vehicle_number' => $request->vehicle_number,
            'reg_date' => $request->reg_date,
            'status' => $request->status ?? true,
        ]);

        if ($request->product_ids) {
            $vehicle->products()->attach($request->product_ids);
        }

        return redirect()->back()->with('success', 'Vehicle created successfully.');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'vehicle_type' => 'nullable|string|max:150',
            'vehicle_name' => 'nullable|string|max:150',
            'vehicle_number' => 'nullable|string|max:50',
            'reg_date' => 'nullable|date',
            'status' => 'boolean'
        ]);

        $vehicle->update([
            'customer_id' => $request->customer_id,
            'vehicle_type' => $request->vehicle_type,
            'vehicle_name' => $request->vehicle_name,
            'vehicle_number' => $request->vehicle_number,
            'reg_date' => $request->reg_date,
            'status' => $request->status ?? true,
        ]);

        $vehicle->products()->sync($request->product_ids ?? []);

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
            'ids.*' => 'exists:vehicles,id'
        ]);

        Vehicle::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' vehicles deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Vehicle::select('id', 'customer_id', 'vehicle_type', 'vehicle_name', 'vehicle_number', 'reg_date', 'status', 'created_at')
            ->with('customer:id,name', 'products:id,product_name');

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('vehicle_name', 'like', '%' . $request->search . '%')
                    ->orWhere('vehicle_number', 'like', '%' . $request->search . '%')
                    ->orWhere('vehicle_type', 'like', '%' . $request->search . '%')
                    ->orWhereHas('customer', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer_id', $request->customer);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $vehicles = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.vehicles', compact('vehicles', 'companySetting'));
        return $pdf->stream('vehicles.pdf');
    }
}
