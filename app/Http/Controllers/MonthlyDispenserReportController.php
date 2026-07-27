<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class MonthlyDispenserReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('is_shift_closes')
            ->join('shifts', 'is_shift_closes.shift_id', '=', 'shifts.id')
            ->select(
                'is_shift_closes.*',
                'shifts.name as shift_name'
            );
            
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('shifts.name', 'like', '%' . $request->search . '%')
                  ->orWhereDate('is_shift_closes.close_date', 'like', '%' . $request->search . '%');
            });
        }
        
        if ($request->start_date) {
            $query->whereDate('is_shift_closes.close_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('is_shift_closes.close_date', '<=', $request->end_date);
        }
        
        $sortBy = $request->sort_by ?? 'close_date';
        $sortOrder = $request->sort_order ?? 'desc';
        $perPage = $request->per_page ?? 10;
        
        $shiftClosedList = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
        
        $products = DB::table('products')
            ->select('id', 'product_name')
            ->where('status', 1)
            ->get();
            
        $readings = $shiftClosedList->getCollection()->map(function ($shift, $index) use ($request, $shiftClosedList) {
            $dailyReading = DB::table('daily_readings')
                ->where('shift_id', $shift->shift_id)
                ->where('date', $shift->close_date)
                ->first();
                
            $voucherData = DB::table('vouchers')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->where('vouchers.shift_id', $shift->shift_id)
                ->whereDate('vouchers.date', $shift->close_date)
                ->selectRaw('SUM(CASE WHEN vouchers.voucher_type = "Receipt" THEN transactions.amount ELSE 0 END) as received_due_paid')
                ->selectRaw('SUM(CASE WHEN vouchers.voucher_type = "Payment" THEN transactions.amount ELSE 0 END) as expenses')
                ->first();
                
            $dispenserSales = DB::table('dispenser_readings')
                ->join('products', 'dispenser_readings.product_id', '=', 'products.id')
                ->where('dispenser_readings.shift_id', $shift->shift_id)
                ->where('dispenser_readings.net_reading', '>', 0)
                ->when($request->product_id, function($q) use ($request) {
                    $q->where('products.id', $request->product_id);
                })
                ->select(
                    'products.id as product_id',
                    'products.product_name',
                    DB::raw('SUM(dispenser_readings.net_reading) as total_sale'),
                    DB::raw('AVG(dispenser_readings.item_rate) as price'),
                    DB::raw('SUM(dispenser_readings.total_sale) as amount')
                )
                ->groupBy('products.id', 'products.product_name')
                ->get();
                
            $otherSales = DB::table('daily_other_product_sales')
                ->join('products', 'daily_other_product_sales.product_id', '=', 'products.id')
                ->where('daily_other_product_sales.shift_id', $shift->shift_id)
                ->whereDate('daily_other_product_sales.date', $shift->close_date)
                ->where('daily_other_product_sales.sell_quantity', '>', 0)
                ->when($request->product_id, function($q) use ($request) {
                    $q->where('products.id', $request->product_id);
                })
                ->select(
                    'products.id as product_id',
                    'products.product_name',
                    DB::raw('SUM(daily_other_product_sales.sell_quantity) as total_sale'),
                    DB::raw('AVG(daily_other_product_sales.item_rate) as price'),
                    DB::raw('SUM(daily_other_product_sales.total_sales) as amount')
                )
                ->groupBy('products.id', 'products.product_name')
                ->get();
                
            $productSales = $dispenserSales->merge($otherSales)->toArray();
                
            return [
                'id' => $shift->id,
                'sl' => ($shiftClosedList->currentPage() - 1) * $shiftClosedList->perPage() + $index + 1,
                'date' => $shift->close_date,
                'shift' => $shift->shift_name,
                'product_sales' => $productSales,
                'received_due_paid' => $voucherData->received_due_paid ?? 0,
                'amount' => $dailyReading->total_cash ?? 0,
                'credit_sale' => $dailyReading->credit_sales ?? 0,
                'bank_sale' => $dailyReading->bank_sales ?? 0,
                'expenses' => $voucherData->expenses ?? 0,
                'purchase' => 0,
                'cash_in_hand' => $dailyReading->cash_payment ?? 0,
                'total_balance' => ($dailyReading->total_cash ?? 0) - ($dailyReading->credit_sales ?? 0) - ($dailyReading->bank_sales ?? 0) - ($voucherData->expenses ?? 0)
            ];
        });
        
        $shiftClosedList->setCollection($readings);

        return Inertia::render('Reports/MonthlyDispenserReport', [
            'readings' => $shiftClosedList,
            'products' => $products->toArray(),
            'filters' => $request->only(['search', 'product_id', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page', 'visible_columns', 'visible_products'])
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $query = DB::table('is_shift_closes')
            ->join('shifts', 'is_shift_closes.shift_id', '=', 'shifts.id')
            ->select(
                'is_shift_closes.*',
                'shifts.name as shift_name'
            );
            
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('shifts.name', 'like', '%' . $request->search . '%')
                  ->orWhereDate('is_shift_closes.close_date', 'like', '%' . $request->search . '%');
            });
        }
        
        if ($request->start_date) {
            $query->whereDate('is_shift_closes.close_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('is_shift_closes.close_date', '<=', $request->end_date);
        }
        
        $sortBy = $request->sort_by ?? 'close_date';
        $sortOrder = $request->sort_order ?? 'desc';
        
        $shiftClosedList = $query->orderBy($sortBy, $sortOrder)->get();
        
        $products = DB::table('products')
            ->select('id', 'product_name')
            ->where('status', 1)
            ->get()->toArray();
            
        $readings = $shiftClosedList->map(function ($shift, $index) use ($request) {
            $dailyReading = DB::table('daily_readings')
                ->where('shift_id', $shift->shift_id)
                ->where('date', $shift->close_date)
                ->first();
                
            $voucherData = DB::table('vouchers')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->where('vouchers.shift_id', $shift->shift_id)
                ->whereDate('vouchers.date', $shift->close_date)
                ->selectRaw('SUM(CASE WHEN vouchers.voucher_type = "Receipt" THEN transactions.amount ELSE 0 END) as received_due_paid')
                ->selectRaw('SUM(CASE WHEN vouchers.voucher_type = "Payment" THEN transactions.amount ELSE 0 END) as expenses')
                ->first();
                
            $dispenserSales = DB::table('dispenser_readings')
                ->join('products', 'dispenser_readings.product_id', '=', 'products.id')
                ->where('dispenser_readings.shift_id', $shift->shift_id)
                ->where('dispenser_readings.net_reading', '>', 0)
                ->when($request->product_id, function($q) use ($request) {
                    $q->where('products.id', $request->product_id);
                })
                ->select(
                    'products.id as product_id',
                    'products.product_name',
                    DB::raw('SUM(dispenser_readings.net_reading) as total_sale'),
                    DB::raw('AVG(dispenser_readings.item_rate) as price'),
                    DB::raw('SUM(dispenser_readings.total_sale) as amount')
                )
                ->groupBy('products.id', 'products.product_name')
                ->get();
                
            $otherSales = DB::table('daily_other_product_sales')
                ->join('products', 'daily_other_product_sales.product_id', '=', 'products.id')
                ->where('daily_other_product_sales.shift_id', $shift->shift_id)
                ->whereDate('daily_other_product_sales.date', $shift->close_date)
                ->where('daily_other_product_sales.sell_quantity', '>', 0)
                ->when($request->product_id, function($q) use ($request) {
                    $q->where('products.id', $request->product_id);
                })
                ->select(
                    'products.id as product_id',
                    'products.product_name',
                    DB::raw('SUM(daily_other_product_sales.sell_quantity) as total_sale'),
                    DB::raw('AVG(daily_other_product_sales.item_rate) as price'),
                    DB::raw('SUM(daily_other_product_sales.total_sales) as amount')
                )
                ->groupBy('products.id', 'products.product_name')
                ->get();
                
            $productSales = $dispenserSales->merge($otherSales)->toArray();
                
            return [
                'id' => $shift->id,
                'sl' => $index + 1,
                'date' => $shift->close_date,
                'shift' => $shift->shift_name,
                'product_sales' => $productSales,
                'received_due_paid' => $voucherData->received_due_paid ?? 0,
                'amount' => $dailyReading->total_cash ?? 0,
                'credit_sale' => $dailyReading->credit_sales ?? 0,
                'bank_sale' => $dailyReading->bank_sales ?? 0,
                'expenses' => $voucherData->expenses ?? 0,
                'purchase' => 0,
                'cash_in_hand' => $dailyReading->cash_payment ?? 0,
                'total_balance' => ($dailyReading->total_cash ?? 0) - ($dailyReading->credit_sales ?? 0) - ($dailyReading->bank_sales ?? 0) - ($voucherData->expenses ?? 0)
            ];
        })->toArray();
        
        $companySetting = CompanySetting::first();
        
        $visibleColumns = [];
        if ($request->visible_columns) {
            $visibleColumns = json_decode($request->visible_columns, true) ?? [];
        }
        
        $visibleProducts = [];
        if ($request->visible_products) {
            $visibleProducts = json_decode($request->visible_products, true) ?? [];
        }

        $pdf = Pdf::loadView('pdf.monthly-dispenser-report', compact('readings', 'products', 'companySetting', 'visibleColumns', 'visibleProducts'));
        return $pdf->stream('monthly-dispenser-report.pdf');
    }
}