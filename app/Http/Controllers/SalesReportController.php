<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SalesReportService $reports
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $reportData = $this->reports->report($filters);
        $filterOptions = $this->reports->filterOptions();

        return Inertia::render('Reports/SalesReportDetails', [
            'report' => $reportData,
            'filters' => $reportData['filters'],
            'customers' => $filterOptions['customers'],
            'vehicles' => $filterOptions['vehicles'],
            'products' => $filterOptions['products'],
            'shifts' => $filterOptions['shifts'],
            'paymentTypes' => $filterOptions['paymentTypes'],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $reportData = $this->reports->report($filters);
        $companySetting = CompanySetting::query()->first();

        $pdf = Pdf::loadView('pdf.sales-report-details', [
            'report' => $reportData,
            'companySetting' => $companySetting,
            'filters' => $reportData['filters'],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('sales-report-details.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'start_date',
            'end_date',
            'customer_id',
            'customer',
            'vehicle_id',
            'vehicle',
            'product_id',
            'payment_type',
            'shift_id',
        ]);
    }
}
