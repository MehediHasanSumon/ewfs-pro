<?php

namespace App\Http\Controllers;

use App\Models\IsShiftClose;
use App\Models\Shift;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ShiftClosedListController extends Controller implements HasMiddleware
{
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
        $query = IsShiftClose::with('shift');

        if ($request->search) {
            $query->whereHas('shift', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->start_date) {
            $query->whereDate('close_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('close_date', '<=', $request->end_date);
        }

        $sortField = $request->sort ?? 'close_date';
        $sortDirection = $request->direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $shiftClosedList = $query->paginate(10);

        $shiftClosedList->getCollection()->transform(function ($item) {
            $item->daily_reading = DB::table('daily_readings')
                ->where('shift_id', $item->shift_id)
                ->whereDate('date', $item->close_date)
                ->first();
            return $item;
        });

        $shifts = Shift::all();

        return Inertia::render('ShiftClosedList/Index', [
            'shiftClosedList' => $shiftClosedList,
            'shifts' => $shifts,
            'filters' => [
                'search' => $request->search,
                'shift_id' => $request->shift_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'sortBy' => $request->sort,
                'direction' => $request->direction,
            ]
        ]);
    }

    public function show($id)
    {
        $shiftClosed = IsShiftClose::with('shift')->findOrFail($id);

        $shiftClosed->daily_reading = DB::table('daily_readings')
            ->where('shift_id', $shiftClosed->shift_id)
            ->whereDate('date', $shiftClosed->close_date)
            ->select(
                'credit_sales',
                'bank_sales',
                'cash_sales',
                'credit_sales_other',
                'bank_sales_other',
                'cash_sales_other',
                'cash_receive',
                'bank_receive',
                'total_cash',
                'cash_payment',
                'bank_payment',
                'office_payment',
                'final_due_amount'
            )
            ->first();

        if ($shiftClosed->daily_reading) {
            $shiftClosed->daily_reading->credit_sales_other = $shiftClosed->daily_reading->credit_sales_other ?? '0.00';
            $shiftClosed->daily_reading->bank_sales_other = $shiftClosed->daily_reading->bank_sales_other ?? '0.00';
            $shiftClosed->daily_reading->cash_sales_other = $shiftClosed->daily_reading->cash_sales_other ?? '0.00';
            $shiftClosed->daily_reading->cash_receive = $shiftClosed->daily_reading->cash_receive ?? '0.00';
            $shiftClosed->daily_reading->bank_receive = $shiftClosed->daily_reading->bank_receive ?? '0.00';
        }

        $shiftClosed->dispenser_readings = DB::table('dispenser_readings')
            ->join('dispensers', 'dispenser_readings.dispenser_id', '=', 'dispensers.id')
            ->join('products', 'dispenser_readings.product_id', '=', 'products.id')
            ->leftJoin('employees', 'dispenser_readings.employee_id', '=', 'employees.id')
            ->where('dispenser_readings.shift_id', $shiftClosed->shift_id)
            ->whereDate('dispenser_readings.transaction_date', $shiftClosed->close_date)
            ->select(
                'dispenser_readings.*',
                'dispensers.dispenser_name',
                'products.product_name',
                'employees.employee_name'
            )
            ->get()
            ->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'dispenser' => ['dispenser_name' => $reading->dispenser_name],
                    'product' => ['product_name' => $reading->product_name],
                    'item_rate' => $reading->item_rate,
                    'start_reading' => $reading->start_reading,
                    'end_reading' => $reading->end_reading,
                    'meter_test' => $reading->meter_test,
                    'net_reading' => $reading->net_reading,
                    'total_sale' => $reading->total_sale,
                    'employee' => ['employee_name' => $reading->employee_name],
                ];
            });

        $shiftClosed->other_product_sales = DB::table('daily_other_product_sales')
            ->join('products', 'daily_other_product_sales.product_id', '=', 'products.id')
            ->join('units', 'daily_other_product_sales.unit_id', '=', 'units.id')
            ->leftJoin('employees', 'daily_other_product_sales.employee_id', '=', 'employees.id')
            ->where('daily_other_product_sales.shift_id', $shiftClosed->shift_id)
            ->whereDate('daily_other_product_sales.date', $shiftClosed->close_date)
            ->select(
                'daily_other_product_sales.*',
                'products.product_name',
                'products.product_code',
                'units.name as unit_name',
                'employees.employee_name'
            )
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'product' => ['product_name' => $sale->product_name, 'product_code' => $sale->product_code],
                    'unit' => ['name' => $sale->unit_name],
                    'item_rate' => $sale->item_rate,
                    'sell_quantity' => $sale->sell_quantity,
                    'total_sales' => $sale->total_sales,
                    'employee' => ['employee_name' => $sale->employee_name],
                ];
            });

        return Inertia::render('ShiftClosedList/Show', [
            'shiftClosed' => $shiftClosed
        ]);
    }

    public function destroy($id)
    {
        $shiftClosed = IsShiftClose::findOrFail($id);

        DB::table('daily_readings')
            ->where('shift_id', $shiftClosed->shift_id)
            ->whereDate('date', $shiftClosed->close_date)
            ->delete();

        DB::table('dispenser_readings')
            ->where('shift_id', $shiftClosed->shift_id)
            ->whereDate('transaction_date', $shiftClosed->close_date)
            ->delete();

        DB::table('daily_other_product_sales')
            ->where('shift_id', $shiftClosed->shift_id)
            ->whereDate('date', $shiftClosed->close_date)
            ->delete();

        $shiftClosed->delete();
        return redirect()->back()->with('success', 'Shift and related data deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:is_shift_closes,id'
        ]);

        $shiftClosedRecords = IsShiftClose::whereIn('id', $request->ids)->get();

        foreach ($shiftClosedRecords as $record) {
            DB::table('daily_readings')
                ->where('shift_id', $record->shift_id)
                ->whereDate('date', $record->close_date)
                ->delete();

            DB::table('dispenser_readings')
                ->where('shift_id', $record->shift_id)
                ->whereDate('transaction_date', $record->close_date)
                ->delete();

            DB::table('daily_other_product_sales')
                ->where('shift_id', $record->shift_id)
                ->whereDate('date', $record->close_date)
                ->delete();
        }

        IsShiftClose::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', 'Selected records and related data deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = IsShiftClose::with('shift');

        if ($request->search) {
            $query->whereHas('shift', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->start_date) {
            $query->whereDate('close_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('close_date', '<=', $request->end_date);
        }

        $allowedSortFields = ['close_date', 'shift_id', 'created_at'];
        $sortField = in_array($request->sort, $allowedSortFields) ? $request->sort : 'close_date';
        $sortDirection = in_array($request->direction, ['asc', 'desc']) ? $request->direction : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $shiftClosedList = $query->get();

        $shiftClosedList->transform(function ($item) {
            $item->daily_reading = DB::table('daily_readings')
                ->where('shift_id', $item->shift_id)
                ->whereDate('date', $item->close_date)
                ->select(
                    'credit_sales',
                    'bank_sales',
                    'cash_sales',
                    'credit_sales_other',
                    'bank_sales_other',
                    'cash_sales_other',
                    'cash_receive',
                    'bank_receive',
                    'total_cash',
                    'cash_payment',
                    'bank_payment',
                    'office_payment',
                    'final_due_amount'
                )
                ->first();
            return $item;
        });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.shift-closed-list', compact('shiftClosedList', 'companySetting'));
        return $pdf->stream('shift-closed-list.pdf');
    }

    public function downloadShowPdf($id)
    {
        $shiftClosed = IsShiftClose::with('shift')->findOrFail($id);

        $shiftClosed->daily_reading = DB::table('daily_readings')
            ->where('shift_id', $shiftClosed->shift_id)
            ->whereDate('date', $shiftClosed->close_date)
            ->select(
                'credit_sales',
                'bank_sales',
                'cash_sales',
                'credit_sales_other',
                'bank_sales_other',
                'cash_sales_other',
                'cash_receive',
                'bank_receive',
                'total_cash',
                'cash_payment',
                'bank_payment',
                'office_payment',
                'final_due_amount'
            )
            ->first();

        // Ensure all fields have default values if null
        if ($shiftClosed->daily_reading) {
            $shiftClosed->daily_reading->credit_sales_other = $shiftClosed->daily_reading->credit_sales_other ?? '0.00';
            $shiftClosed->daily_reading->bank_sales_other = $shiftClosed->daily_reading->bank_sales_other ?? '0.00';
            $shiftClosed->daily_reading->cash_sales_other = $shiftClosed->daily_reading->cash_sales_other ?? '0.00';
            $shiftClosed->daily_reading->cash_receive = $shiftClosed->daily_reading->cash_receive ?? '0.00';
            $shiftClosed->daily_reading->bank_receive = $shiftClosed->daily_reading->bank_receive ?? '0.00';
        }

        $shiftClosed->dispenser_readings = DB::table('dispenser_readings')
            ->join('dispensers', 'dispenser_readings.dispenser_id', '=', 'dispensers.id')
            ->join('products', 'dispenser_readings.product_id', '=', 'products.id')
            ->leftJoin('employees', 'dispenser_readings.employee_id', '=', 'employees.id')
            ->where('dispenser_readings.shift_id', $shiftClosed->shift_id)
            ->whereDate('dispenser_readings.transaction_date', $shiftClosed->close_date)
            ->select(
                'dispenser_readings.*',
                'dispensers.dispenser_name',
                'products.product_name',
                'employees.employee_name'
            )
            ->get()
            ->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'dispenser' => ['dispenser_name' => $reading->dispenser_name],
                    'product' => ['product_name' => $reading->product_name],
                    'item_rate' => $reading->item_rate,
                    'start_reading' => $reading->start_reading,
                    'end_reading' => $reading->end_reading,
                    'meter_test' => $reading->meter_test,
                    'net_reading' => $reading->net_reading,
                    'total_sale' => $reading->total_sale,
                    'employee' => ['employee_name' => $reading->employee_name],
                ];
            });

        $shiftClosed->other_product_sales = DB::table('daily_other_product_sales')
            ->join('products', 'daily_other_product_sales.product_id', '=', 'products.id')
            ->join('units', 'daily_other_product_sales.unit_id', '=', 'units.id')
            ->leftJoin('employees', 'daily_other_product_sales.employee_id', '=', 'employees.id')
            ->where('daily_other_product_sales.shift_id', $shiftClosed->shift_id)
            ->whereDate('daily_other_product_sales.date', $shiftClosed->close_date)
            ->select(
                'daily_other_product_sales.*',
                'products.product_name',
                'products.product_code',
                'units.name as unit_name',
                'employees.employee_name'
            )
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'product' => ['product_name' => $sale->product_name, 'product_code' => $sale->product_code],
                    'unit' => ['name' => $sale->unit_name],
                    'item_rate' => $sale->item_rate,
                    'sell_quantity' => $sale->sell_quantity,
                    'total_sales' => $sale->total_sales,
                    'employee' => ['employee_name' => $sale->employee_name],
                ];
            });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.shift-closed-show', compact('shiftClosed', 'companySetting'));
        return $pdf->stream('shift-details.pdf');
    }
}
