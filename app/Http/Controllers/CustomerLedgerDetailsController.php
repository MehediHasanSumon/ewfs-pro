<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Services\CustomerReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CustomerLedgerDetailsController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CustomerReportService $reports
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-customer', only: ['index']),
            new Middleware('permission:view-customer|can-customer-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request, Customer $customer)
    {
        [$startDate, $endDate] = $this->dates($request);

        return Inertia::render('CustomerLedgerDetails/Index', [
            'ledgers' => $this->reports->ledgerDetails(
                $customer,
                $startDate,
                $endDate
            ),
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function downloadPdf(Request $request, Customer $customer)
    {
        [$startDate, $endDate] = $this->dates($request);
        $ledgers = $this->reports->ledgerDetails(
            $customer,
            $startDate,
            $endDate
        );
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.customer-ledger-details',
            compact('ledgers', 'companySetting', 'startDate', 'endDate')
        )->stream('customer-ledger-details.pdf');
    }

    private function dates(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $validated['start_date'] ?? today()->toDateString(),
            $validated['end_date'] ?? today()->toDateString(),
        ];
    }
}
