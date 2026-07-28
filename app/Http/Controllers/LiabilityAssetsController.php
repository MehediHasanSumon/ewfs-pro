<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\FinancialReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class LiabilityAssetsController extends Controller implements HasMiddleware
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
        $data = $this->reportData($this->asOfDate($request));

        return Inertia::render('LiabilityAssets/Index', $data);
    }

    public function downloadPdf(Request $request)
    {
        $data = $this->reportData($this->asOfDate($request));
        $liabilities = $data['liabilities'];
        $assets = $data['assets'];
        $totalLiabilities = $data['totalLiabilities'];
        $totalAssets = $data['totalAssets'];
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.liability-assets',
            compact(
                'liabilities',
                'assets',
                'totalLiabilities',
                'totalAssets',
                'companySetting'
            )
        )->stream('liability-assets.pdf');
    }

    private function reportData(string $asOfDate): array
    {
        $position = $this->reports->positionSummary($asOfDate);
        $liabilityTotals = $position['liabilities'];
        $assetTotals = $position['assets'];

        $liabilities = collect([
            [
                'name' => 'Purchase Due',
                'group_name' => 'Accounts Payable',
                'balance' => $liabilityTotals['purchase_due'],
                'type' => 'Liability',
            ],
            [
                'name' => 'Customer Advance',
                'group_name' => 'Current Liabilities',
                'balance' => $liabilityTotals['customer_advance'],
                'type' => 'Liability',
            ],
            [
                'name' => 'Customer Security',
                'group_name' => 'Current Liabilities',
                'balance' => $liabilityTotals['customer_security'],
                'type' => 'Liability',
            ],
            [
                'name' => 'Bank Loan',
                'group_name' => 'Long Term Liabilities',
                'balance' => $liabilityTotals['bank_loan'],
                'type' => 'Liability',
            ],
        ]);

        $assets = collect([
            [
                'name' => 'In Stock Product',
                'group_name' => 'Current Assets',
                'balance' => $assetTotals['stock_value'],
                'type' => 'Asset',
            ],
            [
                'name' => 'Customer Due',
                'group_name' => 'Account Receivable',
                'balance' => $assetTotals['customer_due'],
                'type' => 'Asset',
            ],
            [
                'name' => 'Bank Deposit',
                'group_name' => 'Current Assets',
                'balance' => $assetTotals['bank_deposit'],
                'type' => 'Asset',
            ],
            [
                'name' => 'Office Cash',
                'group_name' => 'Current Assets',
                'balance' => $assetTotals['office_cash'],
                'type' => 'Asset',
            ],
        ]);

        return [
            'liabilities' => $liabilities,
            'assets' => $assets,
            'totalLiabilities' => (float) $liabilities->sum('balance'),
            'totalAssets' => (float) $assets->sum('balance'),
        ];
    }

    private function asOfDate(Request $request): string
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        return $validated['date'] ?? today()->toDateString();
    }
}
