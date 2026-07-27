<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Product;
use App\Models\Category;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class StockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-stock', only: ['index']),
            new Middleware('permission:create-stock', only: ['store']),
            new Middleware('permission:update-stock', only: ['update']),
            new Middleware('permission:delete-stock', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-stock-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $query = Stock::with(['product.unit', 'product.category']);

        // Apply filters
        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_name', 'like', '%' . $request->search . '%')
                    ->orWhere('product_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category && $request->category !== 'all') {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'in_stock') {
                $query->where('current_stock', '>', 0);
            } elseif ($request->status === 'out_of_stock') {
                $query->where('current_stock', '<=', 0);
            } elseif ($request->status === 'low_stock') {
                $query->where('current_stock', '>', 0)->where('current_stock', '<=', 10);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 10);
        $stocks = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Stocks/Stocks', [
            'stocks' => $stocks,
            'products' => Product::select('id', 'product_name')->get(),
            'categories' => Category::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'category', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'current_stock' => 'required|numeric|min:0',
            'available_stock' => 'required|numeric|min:0',
        ]);

        Stock::create($request->all());

        return redirect()->back()->with('success', 'Stock created successfully.');
    }

    public function update(Request $request, Stock $stock)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'current_stock' => 'required|numeric|min:0',
            'available_stock' => 'required|numeric|min:0',
        ]);

        $stock->update($request->all());

        return redirect()->back()->with('success', 'Stock updated successfully.');
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();
        return redirect()->back()->with('success', 'Stock deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:stocks,id'
        ]);

        Stock::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' stocks deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Stock::with(['product.unit', 'product.category']);

        // Apply same filters as index method
        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_name', 'like', '%' . $request->search . '%')
                    ->orWhere('product_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category && $request->category !== 'all') {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'in_stock') {
                $query->where('current_stock', '>', 0);
            } elseif ($request->status === 'out_of_stock') {
                $query->where('current_stock', '<=', 0);
            } elseif ($request->status === 'low_stock') {
                $query->where('current_stock', '>', 0)->where('current_stock', '<=', 10);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $stocks = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.stocks', compact('stocks', 'companySetting'));
        return $pdf->stream('stocks.pdf');
    }
}