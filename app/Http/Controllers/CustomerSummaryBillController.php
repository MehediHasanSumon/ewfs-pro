<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerSummaryBillController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-customer', only: ['index']),
            new Middleware('permission:view-customer|can-customer-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $customerId = $request->customer_id;

        $query = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('vehicles', 'credit_sales.vehicle_id', '=', 'vehicles.id')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('credit_sales.customer_id', $customerId);
        }

        $sales = $query->select(
            'customers.id as customer_id',
            'customers.name as customer_name',
            'credit_sales.sale_date',
            'vehicles.vehicle_number',
            'credit_sales.invoice_no',
            'credit_sales.invoice_no as transaction_id',
            'products.product_name',
            'units.name as unit_name',
            'product_rates.sales_price as price',
            'credit_sales.quantity',
            'credit_sales.total_amount'
        )
        ->orderBy('customers.name')
        ->orderBy('credit_sales.sale_date')
        ->get();

        $bills = $sales->groupBy('customer_id')->map(function($items) {
            return [
                'customer_name' => $items->first()->customer_name,
                'sales' => $items->values()->toArray(),
                'total_quantity' => $items->sum('quantity'),
                'total_amount' => $items->sum('total_amount')
            ];
        })->values()->toArray();

        return Inertia::render('CustomerSummaryBill/Index', [
            'bills' => $bills,
            'customers' => Customer::select('id', 'name')->get(),
            'filters' => $request->only(['customer_id', 'start_date', 'end_date'])
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $customerId = $request->customer_id;

        $query = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('vehicles', 'credit_sales.vehicle_id', '=', 'vehicles.id')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('credit_sales.customer_id', $customerId);
        }

        $sales = $query->select(
            'customers.id as customer_id',
            'customers.name as customer_name',
            'credit_sales.sale_date',
            'vehicles.vehicle_number',
            'credit_sales.invoice_no',
            'credit_sales.invoice_no as transaction_id',
            'products.product_name',
            'units.name as unit_name',
            'product_rates.sales_price as price',
            'credit_sales.quantity',
            'credit_sales.total_amount'
        )
        ->orderBy('customers.name')
        ->orderBy('credit_sales.sale_date')
        ->get();

        $bills = $sales->groupBy('customer_id')->map(function($items) {
            return [
                'customer_name' => $items->first()->customer_name,
                'sales' => $items->values()->toArray(),
                'total_quantity' => $items->sum('quantity'),
                'total_amount' => $items->sum('total_amount')
            ];
        })->values()->toArray();

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customer-summary-bill', compact('bills', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('customer-summary-bill.pdf');
    }
}