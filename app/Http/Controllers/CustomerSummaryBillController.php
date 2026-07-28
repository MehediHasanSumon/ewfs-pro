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

class CustomerSummaryBillController extends Controller implements HasMiddleware
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

    public function index(Request $request)
    {
        [$startDate, $endDate, $customerId] = $this->filters($request);

        return Inertia::render('CustomerSummaryBill/Index', [
            'bills' => $this->reports->summaryBills(
                $startDate,
                $endDate,
                $customerId
            ),
            'customers' => $this->customers(),
            'filters' => $request->only([
                'customer_id',
                'start_date',
                'end_date',
            ]),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$startDate, $endDate, $customerId] = $this->filters($request);
        $bills = $this->reports->summaryBills(
            $startDate,
            $endDate,
            $customerId
        );
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.customer-summary-bill',
            compact('bills', 'companySetting', 'startDate', 'endDate')
        )->stream('customer-summary-bill.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $validated['start_date'] ?? today()->toDateString(),
            $validated['end_date'] ?? today()->toDateString(),
            isset($validated['customer_id'])
                ? (int) $validated['customer_id']
                : null,
        ];
    }

    private function customers()
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
