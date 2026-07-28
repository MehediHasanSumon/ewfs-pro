<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vehicle;
use App\Services\VehicleProductAssignmentService;
use App\Services\VehicleSalesContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VehicleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VehicleProductAssignmentService $vehicleProducts,
        private readonly VehicleSalesContextService $salesContext
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-vehicle', only: ['index', 'show']),
            new Middleware(
                'permission:view-sale|create-sale|update-sale|view-credit-sale|create-credit-sale|update-credit-sale',
                only: ['salesContext']
            ),
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
        $vehicles = $query->paginate($perPage)->withQueryString()->through(
            fn (Vehicle $vehicle) => (new VehicleResource($vehicle))->resolve()
        );

        $customers = Customer::where('status', true)->get(['id', 'name']);
        $products = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_name as name']);

        return Inertia::render('Vehicles/Vehicles', [
            'vehicles' => $vehicles,
            'customers' => $customers,
            'products' => $products,
            'vehicleProductLimit' => max(1, (int) config('erp.vehicle_products.max_assigned', 50)),
            'filters' => $request->only(['search', 'customer', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(VehicleRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $vehicle = Vehicle::query()->create([
                'customer_id' => $validated['customer_id'],
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_name' => $validated['vehicle_name'] ?? null,
                'vehicle_number' => $validated['vehicle_number'],
                'reg_date' => $validated['reg_date'] ?? null,
                'status' => $validated['status'] ?? true,
            ]);

            $this->vehicleProducts->sync($vehicle, $validated['products'] ?? []);
        });

        return redirect()->back()->with('success', 'Vehicle created successfully.');
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($vehicle, $validated): void {
            $vehicle->update([
                'customer_id' => $validated['customer_id'],
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_name' => $validated['vehicle_name'] ?? null,
                'vehicle_number' => $validated['vehicle_number'],
                'reg_date' => $validated['reg_date'] ?? null,
                'status' => $validated['status'] ?? true,
            ]);

            $this->vehicleProducts->sync($vehicle, $validated['products'] ?? []);
        });

        return redirect()->back()->with('success', 'Vehicle updated successfully.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load([
            'customer:id,name,proprietor_name,mobile',
            'products:id,product_name',
        ]);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => (new VehicleResource($vehicle))->resolve(),
        ]);
    }

    public function salesContext(Vehicle $vehicle)
    {
        return response()->json($this->salesContext->resolve($vehicle));
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
