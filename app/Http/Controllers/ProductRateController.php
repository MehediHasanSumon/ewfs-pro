<?php

namespace App\Http\Controllers;

use App\Models\ProductRate;
use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProductRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-product-rate', only: ['index']),
            new Middleware('permission:can-product-rate-download', only: ['downloadPdf']),
            new Middleware('permission:create-product-rate', only: ['store']),
            new Middleware('permission:update-product-rate', only: ['update']),
            new Middleware('permission:delete-product-rate', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = ProductRate::with('product');

        if ($request->search) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->start_date) {
            $query->whereDate('effective_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('effective_date', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'effective_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $rates = $query->paginate($perPage)->withQueryString()->through(function ($rate) {
            return [
                'id' => $rate->id,
                'product_id' => $rate->product_id,
                'product' => $rate->product ? $rate->product->product_name : null,
                'purchase_price' => (float) $rate->purchase_price,
                'sales_price' => (float) $rate->sales_price,
                'effective_date' => $rate->effective_date->format('Y-m-d'),
                'status' => (int) $rate->status,
                'created_at' => $rate->created_at->format('Y-m-d'),
            ];
        });

        $products = Product::where('status', 1)->get(['id', 'product_name']);

        return Inertia::render('ProductRates/Index', [
            'rates' => $rates,
            'products' => $products,
            'filters' => $request->only(['search', 'status', 'product_id', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sales_price' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'status' => 'required|boolean'
        ]);

        ProductRate::create([
            'product_id' => $request->product_id,
            'purchase_price' => $request->purchase_price,
            'sales_price' => $request->sales_price,
            'effective_date' => $request->effective_date,
            'status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Product rate created successfully.');
    }

    public function update(Request $request, ProductRate $productRate)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sales_price' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'status' => 'required|boolean'
        ]);

        $productRate->update([
            'product_id' => $request->product_id,
            'purchase_price' => $request->purchase_price,
            'sales_price' => $request->sales_price,
            'effective_date' => $request->effective_date,
            'status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Product rate updated successfully.');
    }

    public function destroy(ProductRate $productRate)
    {
        $productRate->delete();
        return redirect()->back()->with('success', 'Product rate deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:product_rates,id'
        ]);

        ProductRate::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' product rates deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = ProductRate::with('product');

        if ($request->search) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->start_date) {
            $query->whereDate('effective_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('effective_date', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'effective_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $rates = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.product-rates', compact('rates', 'companySetting'));
        return $pdf->stream('product-rates.pdf');
    }
}
