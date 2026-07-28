<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\OperationalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CustomerSalesReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly OperationalReportService $reports
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return Inertia::render('Reports/CustomerSalesReports', [
            'customerSales' => $this->reports->customerSales(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null,
                $filters['customer'] ?? null
            ),
            'customers' => $this->reports->saleCustomerNames(),
            'filters' => $request->only([
                'start_date',
                'end_date',
                'customer',
            ]),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $customerSales = $this->reports->customerSales(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            $filters['customer'] ?? null
        );
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.customer-sales-reports',
            compact('customerSales', 'companySetting')
        )->stream('customer-sales-reports.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'customer' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
