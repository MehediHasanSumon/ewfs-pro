<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class PurchaseReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-purchase', only: ['index']),
            new Middleware('permission:view-purchase|can-purchase-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'product.unit'])
            ->orderBy('purchase_date', 'desc');

        if ($request->filled('start_date')) {
            $query->whereDate('purchase_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchase_date', '<=', $request->end_date);
        }

        $purchases = $query->get();

        // Format purchases data
        $purchaseList = [];
        $totalQuantity = 0;
        $totalAmount = 0;

        foreach ($purchases as $purchase) {
            $purchaseData = [
                'date' => $purchase->purchase_date,
                'invoice_no' => $purchase->invoice_no,
                'memo_no' => $purchase->memo_no,
                'supplier_name' => $purchase->supplier->name,
                'product_name' => $purchase->product->product_name,
                'unit_name' => $purchase->product->unit->name ?? null,
                'quantity' => (float) $purchase->quantity,
                'price' => (float) $purchase->unit_price,
                'total_amount' => (float) $purchase->net_total_amount,
            ];

            $purchaseList[] = $purchaseData;
            $totalQuantity += $purchaseData['quantity'];
            $totalAmount += $purchaseData['total_amount'];
        }

        $report = [
            'purchases' => $purchaseList,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
        ];

        return Inertia::render('Reports/PurchaseReportDetails', [
            'report' => $report,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $query = Purchase::with(['supplier', 'product.unit'])
            ->orderBy('purchase_date', 'desc');

        // Apply filters only if provided
        if ($request->filled('start_date')) {
            $query->whereDate('purchase_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchase_date', '<=', $request->end_date);
        }

        $purchases = $query->get();

        // Format purchases data
        $purchaseList = [];
        foreach ($purchases as $purchase) {
            $purchaseList[] = [
                'date' => $purchase->purchase_date,
                'invoice_no' => $purchase->invoice_no,
                'memo_no' => $purchase->memo_no,
                'supplier_name' => $purchase->supplier->name,
                'product_name' => $purchase->product->product_name,
                'unit_name' => $purchase->product->unit->name ?? null,
                'quantity' => (float) $purchase->quantity,
                'price' => (float) $purchase->unit_price,
                'total_amount' => (float) $purchase->net_total_amount,
            ];
        }

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.purchase-report-details', [
            'purchases' => $purchaseList,
            'companySetting' => $companySetting,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);

        return $pdf->stream('purchase-report-details.pdf');
    }
}
