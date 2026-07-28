<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Services\LedgerQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CashBookLedgerController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LedgerQueryService $ledger
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index', 'show']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf', 'downloadShiftPdf']),
        ];
    }
    public function index(Request $request)
    {
        $query = ShiftClosing::query()
            ->posted()
            ->with(['shift:id,name', 'summary']);

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->start_date) {
            $query->whereDate('business_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('business_date', '<=', $request->end_date);
        }

        $query->orderByDesc('business_date')->orderByDesc('id');

        $closedShifts = $query->get();

        $closedShifts->transform(function (ShiftClosing $item) {
            $item->setAttribute('close_date', $item->business_date->format('Y-m-d'));
            $item->setAttribute('cash_payment', (float) ($item->summary?->cash_payments ?? 0));
            $item->setAttribute('cash_receive', (float) ($item->summary?->cash_receipts ?? 0));
            
            return $item;
        });

        $shifts = Shift::where('status', true)->get();

        return Inertia::render('CashBookLedger/Index', [
            'closedShifts' => $closedShifts,
            'shifts' => $shifts,
            'filters' => $request->only(['shift_id', 'start_date', 'end_date'])
        ]);
    }

    public function show($id)
    {
        $shiftClosed = $this->findClosing($id);
        $cashTransactions = $this->ledger->cashTransactionsForClosing($shiftClosed);

        return Inertia::render('CashBookLedger/Show', [
            'shiftClosed' => $shiftClosed,
            'cashTransactions' => $cashTransactions
        ]);
    }

    public function downloadShiftPdf($id)
    {
        $shiftClosed = $this->findClosing($id);
        $cashTransactions = $this->ledger->cashTransactionsForClosing($shiftClosed);

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.cash-book-shift', compact('shiftClosed', 'cashTransactions', 'companySetting'));
        return $pdf->stream('cash-book-' . $shiftClosed->shift->name . '-' . $shiftClosed->close_date . '.pdf');
    }

    public function downloadPdf(Request $request)
    {
        $query = ShiftClosing::query()
            ->posted()
            ->with(['shift:id,name', 'summary']);

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->start_date) {
            $query->whereDate('business_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('business_date', '<=', $request->end_date);
        }

        $query->orderByDesc('business_date')->orderByDesc('id');

        $closedShifts = $query->get();

        $closedShifts->transform(function (ShiftClosing $item) {
            $item->setAttribute('close_date', $item->business_date->format('Y-m-d'));
            $item->setAttribute('cash_payment', (float) ($item->summary?->cash_payments ?? 0));
            $item->setAttribute('cash_receive', (float) ($item->summary?->cash_receipts ?? 0));
            
            return $item;
        });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.cash-book-ledger', compact('closedShifts', 'companySetting'));
        return $pdf->stream('cash-book-ledger.pdf');
    }

    private function findClosing(int $id): ShiftClosing
    {
        $closing = ShiftClosing::query()
            ->posted()
            ->with('shift:id,name')
            ->findOrFail($id);

        $closing->setAttribute('close_date', $closing->business_date->format('Y-m-d'));

        return $closing;
    }
}
