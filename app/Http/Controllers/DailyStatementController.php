<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Shift;
use App\Services\DailyStatementReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class DailyStatementController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DailyStatementReportService $reports
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        [$startDate, $endDate, $shiftId] = $this->filters($request);
        $report = $this->reports->report($startDate, $endDate, $shiftId);

        return Inertia::render('DailyStatement/Index', [
            ...$report,
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'shifts' => Shift::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->only([
                'search',
                'customer_id',
                'start_date',
                'end_date',
                'shift_id',
            ]),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$startDate, $endDate, $shiftId] = $this->filters($request);
        $report = $this->reports->report($startDate, $endDate, $shiftId);
        $allProductSales = $report['productWiseSales'];
        $cashSales = $report['cashSales'];
        $bankSales = $report['bankSales'];
        $cashBankSales = $report['cashBankSales'];
        $creditSales = $report['creditSales'];
        $customerWiseSales = $report['customerWiseSales'];
        $cashReceived = $report['cashReceived'];
        $cashPayment = $report['cashPayment'];
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.daily-statement',
            compact(
                'allProductSales',
                'cashSales',
                'bankSales',
                'cashBankSales',
                'creditSales',
                'customerWiseSales',
                'cashReceived',
                'cashPayment',
                'companySetting',
                'startDate',
                'endDate'
            )
        )->stream('daily-statement.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ]);

        return [
            $validated['start_date'] ?? today()->toDateString(),
            $validated['end_date'] ?? today()->toDateString(),
            isset($validated['shift_id'])
                ? (int) $validated['shift_id']
                : null,
        ];
    }
}
