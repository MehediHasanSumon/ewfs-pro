<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerSalesReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $query = Sale::with(['shift'])
            ->select('sale_date', 'customer', 'shift_id', 'invoice_no', 'quantity', 'total_amount')
            ->orderBy('sale_date', 'desc');

        // Apply filters
        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        if ($request->filled('customer')) {
            $query->where('customer', 'like', '%' . $request->customer . '%');
        }

        $sales = $query->get();
        
        // Format sales data
        $customerSales = $sales->map(function ($sale) {
            return [
                'sale_date' => $sale->sale_date,
                'customer' => $sale->customer,
                'shift_name' => $sale->shift->name ?? 'N/A',
                'invoice_no' => $sale->invoice_no,
                'quantity' => (float) $sale->quantity,
                'total_amount' => (float) $sale->total_amount,
            ];
        });
        
        $customers = Sale::select('customer')->distinct()->whereNotNull('customer')->pluck('customer');

        return Inertia::render('Reports/CustomerSalesReports', [
            'customerSales' => $customerSales,
            'customers' => $customers,
            'filters' => $request->only(['start_date', 'end_date', 'customer']),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $query = Sale::with(['shift'])
            ->select('sale_date', 'customer', 'shift_id', 'invoice_no', 'quantity', 'total_amount')
            ->orderBy('sale_date', 'desc');

        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        if ($request->filled('customer')) {
            $query->where('customer', 'like', '%' . $request->customer . '%');
        }

        $sales = $query->get();
        
        $customerSales = $sales->map(function ($sale) {
            return [
                'sale_date' => $sale->sale_date,
                'customer' => $sale->customer,
                'shift_name' => $sale->shift->name ?? 'N/A',
                'invoice_no' => $sale->invoice_no,
                'quantity' => (float) $sale->quantity,
                'total_amount' => (float) $sale->total_amount,
            ];
        });

        $companySetting = CompanySetting::first();
        
        $pdf = Pdf::loadView('pdf.customer-sales-reports', compact('customerSales', 'companySetting'));
        return $pdf->stream('customer-sales-reports.pdf');
    }
}
