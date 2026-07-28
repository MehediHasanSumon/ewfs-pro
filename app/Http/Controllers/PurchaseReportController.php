<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\OperationalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PurchaseReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly OperationalReportService $reports
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-purchase', only: ['index']),
            new Middleware('permission:view-purchase|can-purchase-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return Inertia::render('Reports/PurchaseReportDetails', [
            'report' => $this->reports->purchaseReport(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            ),
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->filters($request);
        $report = $this->reports->purchaseReport(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );
        $purchases = $report['purchases'];
        $companySetting = CompanySetting::query()->first();
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        return Pdf::loadView(
            'pdf.purchase-report-details',
            compact('purchases', 'companySetting', 'startDate', 'endDate')
        )->stream('purchase-report-details.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }
}
