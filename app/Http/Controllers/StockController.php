<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Stock;
use App\Services\StockAdjustmentService;
use App\Services\StockQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StockController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StockAdjustmentService $adjustments,
        private readonly StockQueryService $stockQueries
    ) {
    }

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
        $filters = $this->filters($request);
        $stocks = $this->stockQueries
            ->filtered($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return Inertia::render('Stocks/Stocks', [
            'stocks' => $stocks,
            'products' => Product::query()
                ->orderBy('product_name')
                ->get(['id', 'product_name']),
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->only([
                'search',
                'category',
                'status',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                'unique:stocks,product_id',
            ],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'available_stock' => [
                'required',
                'numeric',
                'min:0',
                'lte:current_stock',
            ],
        ]);

        $this->adjustments->create($validated);

        return back()->with('success', 'Stock created successfully.');
    }

    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('stocks', 'product_id')->ignore($stock->id),
            ],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'available_stock' => [
                'required',
                'numeric',
                'min:0',
                'lte:current_stock',
            ],
        ]);

        $this->adjustments->update($stock, $validated);

        return back()->with('success', 'Stock updated successfully.');
    }

    public function destroy(Stock $stock)
    {
        $this->adjustments->delete($stock);

        return back()->with('success', 'Stock deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:stocks,id'],
        ]);

        $this->adjustments->deleteMany($validated['ids']);

        return back()->with(
            'success',
            count($validated['ids']).' stocks deleted successfully.'
        );
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $stocks = $this->stockQueries->filtered($filters)->get();
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.stock-report',
            compact('stocks', 'companySetting')
        )->stream('stocks.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable'],
            'status' => [
                'nullable',
                'in:all,in_stock,out_of_stock,low_stock',
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (
            isset($validated['category'])
            && $validated['category'] !== ''
            && $validated['category'] !== 'all'
        ) {
            $request->validate([
                'category' => ['integer', 'exists:categories,id'],
            ]);
        }

        return $validated;
    }
}
