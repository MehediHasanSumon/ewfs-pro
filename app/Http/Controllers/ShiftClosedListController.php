<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Services\ShiftClosingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class ShiftClosedListController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ShiftClosingService $closings
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-is-shift-close', only: ['index', 'show']),
            new Middleware('permission:view-is-shift-close|can-is-shift-close-download', only: ['downloadPdf', 'downloadShowPdf']),
            new Middleware('permission:delete-is-shift-close', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $shiftClosedList = $this->filteredQuery($request)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ShiftClosing $closing) => $this->legacyShape($closing));

        return Inertia::render('ShiftClosedList/Index', [
            'shiftClosedList' => $shiftClosedList,
            'shifts' => Shift::query()->get(),
            'filters' => [
                'search' => $request->search,
                'shift_id' => $request->shift_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'sortBy' => $request->sort,
                'direction' => $request->direction,
            ],
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('ShiftClosedList/Show', [
            'shiftClosed' => $this->detailedShape(
                ShiftClosing::query()
                    ->with($this->detailRelations())
                    ->findOrFail($id)
            ),
        ]);
    }

    public function destroy(int $id)
    {
        $this->closings->reverse(ShiftClosing::query()->findOrFail($id));

        return back()->with('success', 'Shift closing reversed successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:shift_closings,id'],
        ]);

        ShiftClosing::query()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(fn (ShiftClosing $closing) => $this->closings->reverse($closing));

        return back()->with('success', 'Shift closings reversed successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $shiftClosedList = $this->filteredQuery($request)
            ->get()
            ->map(fn (ShiftClosing $closing) => $this->legacyShape($closing));

        return Pdf::loadView('pdf.shift-closed-list', [
            'shiftClosedList' => $shiftClosedList,
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('shift-closed-list.pdf');
    }

    public function downloadShowPdf(int $id)
    {
        $shiftClosed = $this->detailedShape(
            ShiftClosing::query()
                ->with($this->detailRelations())
                ->findOrFail($id)
        );

        return Pdf::loadView('pdf.shift-closed-show', [
            'shiftClosed' => $shiftClosed,
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('shift-details.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = ShiftClosing::query()
            ->with(['shift', 'summary'])
            ->where('status', 'posted');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('shift', fn ($shift) => $shift->where('name', 'like', "%{$search}%"));
        }

        $query
            ->when($request->shift_id, fn ($q, $shiftId) => $q->where('shift_id', $shiftId))
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('business_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('business_date', '<=', $date));

        $sort = in_array($request->sort, ['business_date', 'shift_id', 'created_at'], true)
            ? $request->sort
            : 'business_date';

        return $query->orderBy($sort, $request->direction === 'asc' ? 'asc' : 'desc');
    }

    private function legacyShape(ShiftClosing $closing): ShiftClosing
    {
        $closing->setAttribute('close_date', $closing->business_date);
        $closing->setAttribute('daily_reading', $this->dailyReading($closing));

        return $closing;
    }

    private function detailedShape(ShiftClosing $closing): ShiftClosing
    {
        $this->legacyShape($closing);
        $closing->setRelation('dispenser_readings', $closing->dispenserReadings);
        $closing->setAttribute(
            'other_product_sales',
            $closing->productItems->map(fn ($item) => [
                'id' => $item->id,
                'product' => [
                    'product_name' => $item->product_name_snapshot,
                    'product_code' => $item->product?->product_code,
                ],
                'unit' => ['name' => $item->unit_name_snapshot],
                'item_rate' => (float) $item->unit_price,
                'sell_quantity' => (float) $item->quantity,
                'total_sales' => (float) $item->line_total,
                'employee' => ['employee_name' => $item->employee?->employee_name],
            ])
        );

        return $closing;
    }

    private function dailyReading(ShiftClosing $closing): object
    {
        $operational = $this->closings->operationalSummary(
            $closing->business_date->format('Y-m-d'),
            $closing->shift_id
        )['getTotalSummeryReport'][0];
        $summary = $closing->summary;
        $fuelSales = (float) ($summary?->fuel_sales ?? 0);
        $otherSales = (float) ($summary?->other_product_sales ?? 0);
        $creditFuel = (float) $operational['total_credit_sales_amount'];
        $creditOther = (float) $operational['total_credit_sales_other_amount'];
        $bankFuel = (float) $operational['total_bank_sale_amount'];
        $bankOther = (float) $operational['total_bank_sales_other_amount'];

        return (object) [
            'credit_sales' => $creditFuel,
            'bank_sales' => $bankFuel,
            'cash_sales' => max(0, $fuelSales - $creditFuel - $bankFuel),
            'credit_sales_other' => $creditOther,
            'bank_sales_other' => $bankOther,
            'cash_sales_other' => max(0, $otherSales - $creditOther - $bankOther),
            'cash_receive' => (float) ($summary?->cash_receipts ?? 0),
            'bank_receive' => (float) ($summary?->bank_receipts ?? 0),
            'total_cash' => (float) ($summary?->expected_cash ?? 0),
            'cash_payment' => (float) ($summary?->cash_payments ?? 0),
            'bank_payment' => (float) ($summary?->bank_payments ?? 0),
            'office_payment' => (float) ($summary?->office_payments ?? 0),
            'final_due_amount' => (float) ($summary?->actual_cash ?? 0),
        ];
    }

    private function detailRelations(): array
    {
        return [
            'shift',
            'summary',
            'dispenserReadings.dispenser',
            'dispenserReadings.product',
            'dispenserReadings.employee',
            'productItems.product',
            'productItems.unit',
            'productItems.employee',
        ];
    }
}
