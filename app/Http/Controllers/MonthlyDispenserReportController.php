<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\MonthlyDispenserReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonthlyDispenserReportController extends Controller
{
    public function __construct(
        private readonly MonthlyDispenserReportService $reports
    ) {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return Inertia::render('Reports/MonthlyDispenserReport', [
            'readings' => $this->reports->paginated($filters),
            'products' => $this->reports->products(),
            'filters' => $request->only([
                'search',
                'product_id',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
                'visible_columns',
                'visible_products',
            ]),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $readings = $this->reports->all($filters)->all();
        $products = $this->reports->products()->all();
        $companySetting = CompanySetting::query()->first();
        $visibleColumns = $this->jsonArray(
            $filters['visible_columns'] ?? null
        );
        $visibleProducts = $this->jsonArray(
            $filters['visible_products'] ?? null
        );

        return Pdf::loadView(
            'pdf.monthly-dispenser-report',
            compact(
                'readings',
                'products',
                'companySetting',
                'visibleColumns',
                'visibleProducts'
            )
        )->stream('monthly-dispenser-report.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'visible_columns' => ['nullable', 'string', 'max:10000'],
            'visible_products' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function jsonArray(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
