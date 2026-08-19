<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerDetailsShortSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CustomerDetailsBillShortSummaryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CustomerDetailsShortSummaryService $shortSummaryService
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
        [$startDate, $endDate, $customerId, $vehicleId] = $this->filters($request);
        $report = $customerId
            ? $this->shortSummaryService->getShortSummary(
                $startDate,
                $endDate,
                $customerId,
                $vehicleId
            )
            : null;

        $vehicles = Vehicle::query()
            ->orderBy('vehicle_number')
            ->get(['id', 'vehicle_number', 'customer_id']);

        return Inertia::render('CustomerDetailsBillShortSummary/Index', [
            'report' => $report,
            'customers' => $this->customers(),
            'vehicles' => $vehicles,
            'filters' => [
                'customer_id' => $customerId ? (string) $customerId : '',
                'vehicle_id' => $vehicleId ? (string) $vehicleId : '',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$startDate, $endDate, $customerId, $vehicleId] = $this->filters($request);
        $report = $customerId
            ? $this->shortSummaryService->getShortSummary(
                $startDate,
                $endDate,
                $customerId,
                $vehicleId
            )
            : null;
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView(
            'pdf.customer-details-bill-short-summary',
            compact('report', 'companySetting', 'startDate', 'endDate')
        )->setPaper('a4', 'portrait')->stream('customer-details-bill-short-summary.pdf');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $validated['start_date'] ?? now()->startOfMonth()->toDateString(),
            $validated['end_date'] ?? today()->toDateString(),
            isset($validated['customer_id']) && $validated['customer_id'] !== ''
                ? (int) $validated['customer_id']
                : null,
            isset($validated['vehicle_id']) && $validated['vehicle_id'] !== ''
                ? (int) $validated['vehicle_id']
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
