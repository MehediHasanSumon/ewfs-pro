<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BalanceSheetController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly FinancialReportService $reports
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        [$asOfDate, $startDate, $endDate] = $this->dates($request);

        return inertia('BalanceSheet/Index', [
            'data' => $this->reports->balanceSheet($asOfDate, $startDate, $endDate),
            'filters' => [
                'date' => $asOfDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$asOfDate, $startDate, $endDate] = $this->dates($request);
        $data = [
            ...$this->reports->balanceSheet($asOfDate, $startDate, $endDate),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return Pdf::loadView('pdf.balance-sheet', compact('data'))
            ->stream('balance-sheet-'.$startDate.'-to-'.$endDate.'.pdf');
    }

    private function dates(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date']
            ?? now()->startOfYear()->toDateString();
        $endDate = $validated['end_date']
            ?? now()->addDay()->toDateString();
        $asOfDate = $validated['date'] ?? min(today()->toDateString(), $endDate);

        return [$asOfDate, $startDate, $endDate];
    }
}
