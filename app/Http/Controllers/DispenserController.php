<?php

namespace App\Http\Controllers;

use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class DispenserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-dispenser', only: ['index']),
            new Middleware('permission:view-dispenser|can-dispenser-download', only: ['downloadPdf']),
            new Middleware('permission:create-dispenser', only: ['store']),
            new Middleware('permission:update-dispenser', only: ['update']),
            new Middleware('permission:delete-dispenser', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Dispenser::with(['product', 'readings' => function ($q) {
            $q->latest();
        }]);

        if ($request->search) {
            $query->where('dispenser_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true' || $request->status === '1');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'dispenser_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $dispensers = $query->paginate($perPage)->withQueryString()->through(function ($dispenser) {
            $latestReading = $dispenser->readings->first();

            $currentRate = ProductRate::where('product_id', $dispenser->product_id)
                ->where('status', 1)
                ->where('effective_date', '<=', now()->toDateString())
                ->orderBy('effective_date', 'desc')
                ->value('sales_price');

            return [
                'id' => $dispenser->id,
                'dispenser_name' => $dispenser->dispenser_name,
                'product_id' => $dispenser->product_id,
                'product_name' => $dispenser->product->product_name ?? '',
                'dispenser_item' => $dispenser->dispenser_item,
                'item_rate' => $currentRate ?? ($latestReading->item_rate ?? null),
                'start_reading' => $latestReading->start_reading ?? null,
                'end_reading' => $latestReading->end_reading ?? null,
                'status' => $dispenser->status,
                'created_at' => $dispenser->created_at->format('Y-m-d'),
            ];
        });

        $products = Product::select('id', 'product_name')
            ->whereHas('category', function ($query) {
                $query->where('code', '1001');
            })
            ->get();

        return Inertia::render('Dispensers/Dispensers', [
            'dispensers' => $dispensers,
            'products' => $products,
            'filters' => $request->only(['search', 'status', 'product_id', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'dispenser_name' => 'required|string|max:255',
            'status' => 'required|boolean'
        ]);

        $input = array_map('strip_tags', $request->all());
        $input['dispenser_item'] = $input['product_id'];
        $input['status'] = $request->status;

        $dispenser = Dispenser::create($input);

        $product_id = $input['product_id'];
        $product_rate = ProductRate::where('product_id', $product_id)->where('status', 1)->latest('effective_date')->value('sales_price');

        $opening_reading = $request->filled('opening_reading') ? $input['opening_reading'] : 0.00;
        
        DispenserReading::create([
            'shift_id' => null,
            'employee_id' => null,
            'dispenser_id' => $dispenser->id,
            'product_id' => $input['product_id'],
            'start_reading' => $opening_reading,
            'end_reading' => $opening_reading,
            'meter_test' => 0.00,
            'net_reading' => 0.00,
            'item_rate' => $request->filled('item_rate') ? $input['item_rate'] : $product_rate,
            'total_sale' => 0.00,
        ]);

        return redirect()->back()->with('success', 'Dispenser created successfully.');
    }

    public function update(Request $request, Dispenser $dispenser)
    {
        $request->validate([
            'dispenser_name' => 'required|string|max:150',
            'product_id' => 'required|exists:products,id',
            'status' => 'required|boolean'
        ]);

        $dispenser->update([
            'dispenser_name' => $request->dispenser_name,
            'product_id' => $request->product_id,
            'dispenser_item' => $request->product_id,
            'status' => $request->status,
        ]);

        if ($request->filled('opening_reading')) {
            $latestReading = $dispenser->readings()->latest()->first();
            if ($latestReading) {
                $latestReading->update(['end_reading' => $request->opening_reading]);
            }
        }

        return redirect()->back()->with('success', 'Dispenser updated successfully.');
    }

    public function destroy(Dispenser $dispenser)
    {
        $dispenser->delete();
        return redirect()->back()->with('success', 'Dispenser deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:dispensers,id'
        ]);

        Dispenser::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' dispensers deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Dispenser::with('product');

        if ($request->search) {
            $query->where('dispenser_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'true' || $request->status === '1');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'dispenser_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $dispensers = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.dispensers', compact('dispensers', 'companySetting'));
        return $pdf->stream('dispensers.pdf');
    }
}
