<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-product', only: ['index']),
            new Middleware('permission:can-product-download', only: ['downloadPdf']),
            new Middleware('permission:create-product', only: ['store']),
            new Middleware('permission:update-product', only: ['update']),
            new Middleware('permission:delete-product', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Product::with(['category', 'unit']);

        if ($request->search) {
            $query->where('product_name', 'like', '%' . $request->search . '%')
                  ->orWhere('product_code', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->unit_id) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'product_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage)->withQueryString()->through(function ($product) {
            $activeRate = $product->activeRate;
            return [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'unit_id' => $product->unit_id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'product_slug' => $product->product_slug,
                'country_Of_origin' => $product->country_Of_origin,
                'category' => $product->category ? $product->category->name : null,
                'unit' => $product->unit ? $product->unit->name : null,
                'purchase_price' => $activeRate ? (float) $activeRate->purchase_price : null,
                'sales_price' => $activeRate ? (float) $activeRate->sales_price : null,
                'remarks' => $product->remarks,
                'status' => $product->status,
                'created_at' => $product->created_at->format('Y-m-d'),
            ];
        });

        $categories = Category::where('status', true)->get(['id', 'name']);
        $units = Unit::where('status', true)->get(['id', 'name']);

        return Inertia::render('Products/Products', [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'filters' => $request->only(['search', 'status', 'category_id', 'unit_id', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'product_code' => 'nullable|string|max:255',
            'product_name' => 'required|string|max:255',
            'product_slug' => 'nullable|string|max:255',
            'country_Of_origin' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'status' => 'integer|in:0,1'
        ]);

        Product::create($request->all());

        return redirect()->back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'product_code' => 'nullable|string|max:255',
            'product_name' => 'required|string|max:255',
            'product_slug' => 'nullable|string|max:255',
            'country_Of_origin' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'status' => 'integer|in:0,1'
        ]);

        $product->update($request->all());

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id'
        ]);

        Product::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' products deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Product::with(['category', 'unit']);

        if ($request->search) {
            $query->where('product_name', 'like', '%' . $request->search . '%')
                  ->orWhere('product_code', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->unit_id) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'product_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $products = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.products', compact('products', 'companySetting'));
        return $pdf->stream('products.pdf');
    }
}