<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Services\StockQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class StockReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StockQueryService $stockQueries
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-stock', only: ['index']),
            new Middleware('permission:view-stock|can-stock-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $stocks = $this->stockQueries
            ->filtered($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return Inertia::render('StockReport/Index', [
            'stocks' => $stocks,
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->only([
                'search',
                'category',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $stocks = $this->stockQueries->filtered($filters)->get();
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.stock-report',
            compact('stocks', 'companySetting')
        )->stream('stock-report.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable'],
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
