<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class ProductController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ProductService $productService) {}

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
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $products = $this->filteredQuery($request)
            ->with([
                'category:id,name',
                'unit:id,name',
                'activeRate',
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'unit_id' => $product->unit_id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'product_slug' => $product->product_slug,
                'country_Of_origin' => $product->country_of_origin,
                'category' => $product->category?->name,
                'unit' => $product->unit?->name,
                'purchase_price' => $product->activeRate !== null
                    ? (float) $product->activeRate->purchase_price
                    : null,
                'sales_price' => $product->activeRate !== null
                    ? (float) $product->activeRate->sales_price
                    : null,
                'remarks' => $product->remarks,
                'status' => $product->status,
                'created_at' => $product->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Products/Products', [
            'products' => $products,
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only([
                'search',
                'status',
                'category_id',
                'unit_id',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(ProductRequest $request)
    {
        Product::create($request->attributesForPersistence());

        return redirect()->back()->with('success', 'Product created successfully.');
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->attributesForPersistence());

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
        ]);

        $deleted = $this->productService->deleteMany($validated['ids']);

        return redirect()->back()->with('success', "{$deleted} products deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $products = $this->filteredQuery($request)
            ->with(['category', 'unit', 'activeRate'])
            ->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.products', compact('products', 'companySetting'));

        return $pdf->stream('products.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        $allowedSorts = ['id', 'product_name', 'product_code', 'category_id', 'unit_id', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'product_name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
