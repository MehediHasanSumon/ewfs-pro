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
        [$date, $shiftId] = $this->filters($request);
        $report = $this->reports->report($date, $shiftId);

        return Inertia::render('DailyStatement/Index', [
            ...$report,
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'shifts' => Shift::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'date' => $date,
                'start_date' => $date,
                'end_date' => $date,
                'shift_id' => $shiftId ? (string) $shiftId : 'all',
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$date, $shiftId] = $this->filters($request);
        $report = $this->reports->report($date, $shiftId);
        $allProductSales = $report['productWiseSales'];
        $cashSales = $report['cashSales'];
        $bankSales = $report['bankSales'];
        $cashBankSales = $report['cashBankSales'];
        $creditSales = $report['creditSales'];
        $customerWiseSales = $report['customerWiseSales'];
        $cashReceived = $report['cashReceived'];
        $cashPayment = $report['cashPayment'];
        $officePayment = $report['officePayment'];
        $companySetting = CompanySetting::query()->first();
        $selectedShift = $shiftId ? Shift::find($shiftId) : null;
        $startDate = $date;
        $endDate = $date;

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
                'officePayment',
                'companySetting',
                'date',
                'selectedShift',
                'startDate',
                'endDate'
            )
        )->stream('daily-statement.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'shift_id' => ['nullable'],
        ]);

        $date = $validated['date']
            ?? $validated['start_date']
            ?? today()->toDateString();

        $shiftId = isset($validated['shift_id']) && $validated['shift_id'] !== 'all' && $validated['shift_id'] !== ''
            ? (int) $validated['shift_id']
            : null;

        return [
            $date,
            $shiftId,
        ];
    }
}
