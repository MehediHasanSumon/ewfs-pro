<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Shift;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class DailyStatementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $shiftId = $request->shift_id;

        // 1. Sales Summary (Product Wise) - All sales
        $productWiseSales = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $creditProductWiseSales = DB::table('credit_sales')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(credit_sales.quantity) as total_quantity'),
                DB::raw('SUM(credit_sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $allProductSales = $productWiseSales->concat($creditProductWiseSales)
            ->groupBy('product_name')
            ->map(function ($items) {
                return [
                    'product_name' => $items->first()->product_name,
                    'unit_name' => $items->first()->unit_name,
                    'unit_price' => $items->first()->unit_price,
                    'total_quantity' => $items->sum('total_quantity'),
                    'total_amount' => $items->sum('total_amount'),
                ];
            })->values();

        // 2. Sales Summary Cash & Bank - Only cash sales
        $cashBankSales = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        // 3. Sales Summary Credit - Only credit sales
        $creditSales = DB::table('credit_sales')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(credit_sales.quantity) as total_quantity'),
                DB::raw('SUM(credit_sales.due_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        // 4. Customer Wise Sales Summary (Credit)
        $customerWiseSales = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('vehicles', 'credit_sales.vehicle_id', '=', 'vehicles.id')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'customers.name as customer_name',
                'vehicles.vehicle_number as vehicle_no',
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                'credit_sales.quantity',
                'credit_sales.total_amount'
            )
            ->get();

        // 5. Cash Received Summary
        $cashReceived = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('vouchers.shift_id', $shiftId);
            })
            ->select(
                'accounts.name as account_name',
                'transactions.payment_type',
                'transactions.amount'
            )
            ->get();

        // 6. Cash Payment Summary
        $cashPayment = DB::table('vouchers')
            ->join('accounts', 'vouchers.to_account_id', '=', 'accounts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Payment')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('vouchers.shift_id', $shiftId);
            })
            ->select(
                'accounts.name as account_name',
                'transactions.payment_type',
                'transactions.amount'
            )
            ->get();

        return Inertia::render('DailyStatement/Index', [
            'productWiseSales' => $allProductSales,
            'cashBankSales' => $cashBankSales,
            'creditSales' => $creditSales,
            'customerWiseSales' => $customerWiseSales,
            'cashReceived' => $cashReceived,
            'cashPayment' => $cashPayment,
            'customers' => Customer::select('id', 'name')->get(),
            'shifts' => Shift::select('id', 'name')->where('status', true)->get(),
            'filters' => $request->only(['search', 'customer_id', 'start_date', 'end_date', 'shift_id'])
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $shiftId = $request->shift_id;

        $productWiseSales = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $creditProductWiseSales = DB::table('credit_sales')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(credit_sales.quantity) as total_quantity'),
                DB::raw('SUM(credit_sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $allProductSales = $productWiseSales->concat($creditProductWiseSales)
            ->groupBy('product_name')
            ->map(function ($items) {
                return [
                    'product_name' => $items->first()->product_name,
                    'unit_name' => $items->first()->unit_name,
                    'unit_price' => $items->first()->unit_price,
                    'total_quantity' => $items->sum('total_quantity'),
                    'total_amount' => $items->sum('total_amount'),
                ];
            })->values();

        $cashBankSales = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.total_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $creditSales = DB::table('credit_sales')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                DB::raw('SUM(credit_sales.quantity) as total_quantity'),
                DB::raw('SUM(credit_sales.due_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.product_name', 'units.name', 'product_rates.sales_price')
            ->get();

        $customerWiseSales = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('vehicles', 'credit_sales.vehicle_id', '=', 'vehicles.id')
            ->join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->join('product_rates', function($join) {
                $join->on('products.id', '=', 'product_rates.product_id')
                     ->where('product_rates.status', true);
            })
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('credit_sales.shift_id', $shiftId);
            })
            ->select(
                'customers.name as customer_name',
                'vehicles.vehicle_number as vehicle_no',
                'products.product_name',
                'units.name as unit_name',
                'product_rates.sales_price as unit_price',
                'credit_sales.quantity',
                'credit_sales.total_amount'
            )
            ->get();

        $cashReceived = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('vouchers.shift_id', $shiftId);
            })
            ->select(
                'accounts.name as account_name',
                'transactions.payment_type',
                'transactions.amount'
            )
            ->get();

        $cashPayment = DB::table('vouchers')
            ->join('accounts', 'vouchers.to_account_id', '=', 'accounts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Payment')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->when($shiftId, function($query) use ($shiftId) {
                return $query->where('vouchers.shift_id', $shiftId);
            })
            ->select(
                'accounts.name as account_name',
                'transactions.payment_type',
                'transactions.amount'
            )
            ->get();

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.daily-statement', compact(
            'allProductSales',
            'cashBankSales',
            'creditSales',
            'customerWiseSales',
            'cashReceived',
            'cashPayment',
            'companySetting',
            'startDate',
            'endDate'
        ));
        return $pdf->stream('daily-statement.pdf');
    }
}