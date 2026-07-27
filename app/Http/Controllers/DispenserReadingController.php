<?php

namespace App\Http\Controllers;

use App\Models\DispenserReading;
use App\Models\Shift;
use App\Models\Product;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\IsShiftClose;
use App\Models\DailyReading;
use App\Models\DailyOtherProductSale;
use App\Models\Stock;
use App\Models\VoucherCategory;
use App\Models\PaymentSubType;
use App\Models\ProductRate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DispenserReadingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-dispenser', only: ['index', 'getShiftsByDate', 'getShiftClosingData']),
            new Middleware('permission:create-dispenser', only: ['store']),
        ];
    }
    public function index()
    {
        $dispenserReading = DispenserReading::with(['product', 'dispenser'])
            ->orderBy('dispenser_id')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('dispenser_id')
            ->map(function ($items) {
                $latest = $items->first();
                if ($latest->end_reading == 0) {
                    $latest->start_reading = $latest->start_reading;
                } else {
                    $latest->start_reading = $latest->end_reading;
                }
                $latest->meter_test = 0;
                $latest->product_id = $latest->product_id ?? ($latest->dispenser->product_id ?? null);

                if ($latest->product_id) {
                    $currentRate = ProductRate::where('product_id', $latest->product_id)
                        ->where('status', 1)
                        ->where('effective_date', '<=', now()->toDateString())
                        ->orderBy('effective_date', 'desc')
                        ->value('sales_price');
                    $latest->item_rate = $currentRate ?? $latest->item_rate;
                }

                return $latest;
            })
            ->values();

        $shifts = Shift::where('status', 1)->get();
        $products = Product::with(['unit', 'stock', 'activeRate'])->where('status', 1)->get()->map(function ($product) {
            $product->sales_price = $product->activeRate ? $product->activeRate->sales_price : 0;
            return $product;
        });
        $otherProducts = Product::with(['unit', 'stock', 'activeRate', 'category'])
            ->where('status', 1)
            ->whereHas('category', function ($query) {
                $query->where('code', '!=', '1001');
            })
            ->get()
            ->map(function ($product) {
                $product->sales_price = $product->activeRate ? $product->activeRate->sales_price : 0;
                return $product;
            });
        $customers = Customer::where('status', true)->select('id', 'name')->get();
        $vehicles = Vehicle::with(['customer:id,name', 'products:id,product_name'])->select('id', 'vehicle_number', 'customer_id')->get();
        $accounts = Account::with('group')->select('id', 'name', 'ac_number', 'group_id', 'group_code')->get();
        $groupedAccounts = $accounts->groupBy(function ($account) {
            return $account->group ? $account->group->name : 'Other';
        });

        $uniqueCustomers = DB::table('sales')
            ->select('customer')
            ->distinct()
            ->whereNotNull('customer')
            ->pluck('customer')
            ->merge(
                DB::table('credit_sales')
                    ->join('accounts', 'credit_sales.customer_id', '=', 'accounts.id')
                    ->select('accounts.name as customer')
                    ->distinct()
                    ->pluck('customer')
            )
            ->unique()
            ->values();

        $uniqueVehicles = DB::table('sales')
            ->select('vehicle_no')
            ->distinct()
            ->whereNotNull('vehicle_no')
            ->pluck('vehicle_no')
            ->merge(
                DB::table('credit_sales')
                    ->join('vehicles', 'credit_sales.vehicle_id', '=', 'vehicles.id')
                    ->select('vehicles.vehicle_number as vehicle_no')
                    ->distinct()
                    ->pluck('vehicle_no')
            )
            ->unique()
            ->values();

        $closedShifts = IsShiftClose::select('close_date', 'shift_id')->get()->map(function ($item) {
            return [
                'close_date' => $item->close_date->format('Y-m-d'),
                'shift_id' => $item->shift_id
            ];
        });
        $employees = Employee::select('id', 'employee_name')->get();
        $voucherCategories = VoucherCategory::where('status', true)->get();
        $paymentSubTypes = PaymentSubType::with('voucherCategory')->where('status', true)->get();

        return Inertia::render('Dispensers/DispensersReading', [
            'dispenserReading' => $dispenserReading,
            'shifts' => $shifts,
            'closedShifts' => $closedShifts,
            'products' => $products,
            'otherProducts' => $otherProducts,
            'customers' => $customers,
            'vehicles' => $vehicles,
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'employees' => $employees,
            'uniqueCustomers' => $uniqueCustomers,
            'uniqueVehicles' => $uniqueVehicles,
            'voucherCategories' => $voucherCategories,
            'paymentSubTypes' => $paymentSubTypes,
        ]);
    }

    public function getShiftsByDate($date)
    {
        $shifts = Shift::where('status', 1)->get();
        return response()->json($shifts);
    }

    public function getShiftClosingData($date, $shiftId)
    {
        try {
            $creditSales = DB::table('credit_sales')
                ->where('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->where('category_code', '1001')
                ->sum('total_amount');

            $bankSales = DB::table('sales')
                ->join('transactions', 'sales.transaction_id', '=', 'transactions.id')
                ->where('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->where('sales.category_code', '1001')
                ->whereIn('transactions.payment_type', ['bank', 'mobile bank'])
                ->sum('sales.total_amount');

            $creditSalesOther = DB::table('credit_sales')
                ->where('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->where('category_code', '!=', '1001')
                ->sum('total_amount');

            $bankSalesOther = DB::table('sales')
                ->join('transactions', 'sales.transaction_id', '=', 'transactions.id')
                ->where('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->where('sales.category_code', '!=', '1001')
                ->whereIn('transactions.payment_type', ['bank', 'mobile bank'])
                ->sum('sales.total_amount');

            $cashReceive = DB::table('vouchers')
                ->where('date', $date)
                ->where('shift_id', $shiftId)
                ->where('voucher_type', 'Receipt')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount');

            $cashPayment = DB::table('vouchers')
                ->where('date', $date)
                ->where('shift_id', $shiftId)
                ->where('voucher_type', 'Payment')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount');

            $officePayment = DB::table('office_payments')
                ->where('date', $date)
                ->where('shift_id', $shiftId)
                ->join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount');

            $creditSalesDetails = DB::table('credit_sales')
                ->where('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->select(
                    'product_id',
                    DB::raw('SUM(total_amount) as product_wise_credit_sales')
                )
                ->groupBy('product_id')
                ->get();

            return response()->json([
                'getTotalSummeryReport' => [
                    [
                        'total_credit_sales_amount' => $creditSales,
                        'total_bank_sale_amount' => $bankSales,
                        'total_cash_receive_amount' => $cashReceive,
                        'total_cash_payment_amount' => $cashPayment,
                        'total_office_payment_amount' => $officePayment,
                        'total_credit_sales_other_amount' => $creditSalesOther,
                        'total_bank_sales_other_amount' => $bankSalesOther,
                    ]
                ],
                'getCreditSalesDetailsReport' => $creditSalesDetails
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'dispenser_readings' => 'required|array',
            'other_product_sales' => 'nullable|array',
            'other_product_sales.*.product_id' => 'required|exists:products,id',
            'other_product_sales.*.quantity' => 'required|numeric|min:0',
            'other_product_sales.*.unit_price' => 'required|numeric|min:0',
            'other_product_sales.*.employee_id' => 'required|exists:employees,id',
            'credit_sales' => 'required|numeric|min:0',
            'bank_sales' => 'required|numeric|min:0',
            'cash_sales' => 'required|numeric|min:0',
            'credit_sales_other' => 'required|numeric|min:0',
            'bank_sales_other' => 'required|numeric|min:0',
            'cash_sales_other' => 'required|numeric|min:0',
            'cash_receive' => 'required|numeric|min:0',
            'bank_receive' => 'nullable|numeric|min:0',
            'total_cash' => 'required|numeric|min:0',
            'cash_payment' => 'required|numeric|min:0',
            'bank_payment' => 'nullable|numeric|min:0',
            'office_payment' => 'required|numeric|min:0',
            'final_due_amount' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->dispenser_readings as $reading) {
                DispenserReading::create([
                    'transaction_date' => $request->transaction_date,
                    'shift_id' => $request->shift_id,
                    'employee_id' => $reading['reading_by'] ?? Auth::id(),
                    'dispenser_id' => $reading['dispenser_id'],
                    'product_id' => $reading['product_id'],
                    'start_reading' => $reading['start_reading'],
                    'end_reading' => $reading['end_reading'],
                    'meter_test' => $reading['meter_test'],
                    'net_reading' => $reading['net_reading'],
                    'item_rate' => $reading['item_rate'],
                    'total_sale' => $reading['total_sale'],
                ]);
            }

            if ($request->has('other_product_sales') && is_array($request->other_product_sales)) {
                foreach ($request->other_product_sales as $sale) {
                    if (isset($sale['quantity']) && $sale['quantity'] > 0) {
                        $product = Product::with('unit')->find($sale['product_id']);

                        DailyOtherProductSale::create([
                            'date' => $request->transaction_date,
                            'shift_id' => $request->shift_id,
                            'product_id' => $sale['product_id'],
                            'unit_id' => $product->unit_id,
                            'item_rate' => $sale['unit_price'],
                            'sell_quantity' => $sale['quantity'],
                            'employee_id' => $sale['employee_id'],
                            'total_sales' => $sale['quantity'] * $sale['unit_price'],
                        ]);

                        $stock = Stock::where('product_id', $sale['product_id'])->first();
                        if ($stock) {
                            $stock->decrement('current_stock', $sale['quantity']);
                            $stock->decrement('available_stock', $sale['quantity']);
                        }
                    }
                }
            }

            DailyReading::create([
                'date' => $request->transaction_date,
                'shift_id' => $request->shift_id,
                'employee_id' => Auth::id(),
                'credit_sales' => $request->credit_sales ?? 0,
                'bank_sales' => $request->bank_sales ?? 0,
                'cash_sales' => $request->cash_sales ?? 0,
                'credit_sales_other' => $request->credit_sales_other ?? 0,
                'bank_sales_other' => $request->bank_sales_other ?? 0,
                'cash_sales_other' => $request->cash_sales_other ?? 0,
                'cash_receive' => $request->cash_receive ?? 0,
                'bank_receive' => 0,
                'total_cash' => $request->total_cash ?? 0,
                'cash_payment' => $request->cash_payment ?? 0,
                'bank_payment' => 0,
                'office_payment' => $request->office_payment ?? 0,
                'final_due_amount' => $request->final_due_amount ?? 0,
            ]);

            IsShiftClose::create([
                'close_date' => $request->transaction_date,
                'shift_id' => $request->shift_id,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Shift closed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to close shift.');
        }
    }
}
