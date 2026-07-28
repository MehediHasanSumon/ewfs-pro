<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditSaleCustomer;
use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Employee;
use App\Models\PaymentSubType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Vehicle;
use App\Models\VoucherCategory;
use App\Models\Customer;
use App\Services\ShiftClosingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class DispenserReadingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ShiftClosingService $closings
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-dispenser', only: ['index', 'getShiftsByDate', 'getShiftClosingData']),
            new Middleware('permission:create-dispenser', only: ['store']),
        ];
    }

    public function index()
    {
        $dispenserReading = Dispenser::query()
            ->with(['product.activeRate', 'latestReading'])
            ->where('status', true)
            ->orderBy('id')
            ->get()
            ->map(function (Dispenser $dispenser) {
                $latest = $dispenser->latestReading;
                $reading = new DispenserReading([
                    'dispenser_id' => $dispenser->id,
                    'product_id' => $dispenser->product_id,
                    'start_reading' => $latest?->end_reading ?? $dispenser->opening_reading,
                    'end_reading' => $latest?->end_reading ?? $dispenser->opening_reading,
                    'meter_test' => 0,
                    'net_quantity' => 0,
                    'unit_price' => $dispenser->product->activeRate?->sales_price ?? 0,
                    'gross_amount' => 0,
                ]);
                $reading->setAttribute('id', $latest?->id);
                $reading->setRelation('dispenser', $dispenser);
                $reading->setRelation('product', $dispenser->product);

                return $reading;
            });

        $products = Product::query()
            ->with(['unit', 'stock', 'activeRate'])
            ->active()
            ->get()
            ->each(fn (Product $product) => $product->setAttribute(
                'sales_price',
                (float) ($product->activeRate?->sales_price ?? 0)
            ));
        $otherProducts = Product::query()
            ->with(['unit', 'stock', 'activeRate', 'category'])
            ->active()
            ->whereHas('category', fn ($category) => $category->where('inventory_class', '!=', 'fuel'))
            ->get()
            ->each(fn (Product $product) => $product->setAttribute(
                'sales_price',
                (float) ($product->activeRate?->sales_price ?? 0)
            ));
        $accounts = Account::query()
            ->with('group:id,name')
            ->active()
            ->get(['id', 'name', 'ac_number', 'group_id']);

        return Inertia::render('Dispensers/DispensersReading', [
            'dispenserReading' => $dispenserReading,
            'shifts' => Shift::query()->where('status', true)->get(),
            'closedShifts' => ShiftClosing::query()
                ->posted()
                ->get(['business_date', 'shift_id'])
                ->map(fn (ShiftClosing $closing) => [
                    'close_date' => $closing->business_date->format('Y-m-d'),
                    'shift_id' => $closing->shift_id,
                ]),
            'products' => $products,
            'otherProducts' => $otherProducts,
            'customers' => Customer::query()->active()->get(['id', 'name']),
            'vehicles' => Vehicle::query()
                ->with(['customer:id,name', 'products:id,product_name'])
                ->get(['id', 'vehicle_number', 'customer_id']),
            'accounts' => $accounts,
            'groupedAccounts' => $accounts->groupBy(fn (Account $account) => $account->group?->name ?? 'Other'),
            'employees' => Employee::query()->where('status', true)->get(['id', 'employee_name']),
            'uniqueCustomers' => Sale::query()
                ->whereNotNull('customer_name_snapshot')
                ->pluck('customer_name_snapshot')
                ->merge(CreditSaleCustomer::query()->pluck('customer_name_snapshot'))
                ->filter()
                ->unique()
                ->values(),
            'uniqueVehicles' => Sale::query()
                ->whereNotNull('vehicle_number_snapshot')
                ->pluck('vehicle_number_snapshot')
                ->merge(Vehicle::query()->pluck('vehicle_number'))
                ->filter()
                ->unique()
                ->values(),
            'voucherCategories' => VoucherCategory::query()->where('status', true)->get(),
            'paymentSubTypes' => PaymentSubType::query()
                ->with('voucherCategory')
                ->where('status', true)
                ->get(),
        ]);
    }

    public function getShiftsByDate(string $date)
    {
        $closedShiftIds = ShiftClosing::query()
            ->whereDate('business_date', $date)
            ->where('status', 'posted')
            ->pluck('shift_id');

        return response()->json(
            Shift::query()
                ->where('status', true)
                ->whereNotIn('id', $closedShiftIds)
                ->get()
        );
    }

    public function getShiftClosingData(string $date, int $shift)
    {
        return response()->json($this->closings->operationalSummary($date, $shift));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'dispenser_readings' => ['required', 'array', 'min:1'],
            'dispenser_readings.*.dispenser_id' => ['required', 'exists:dispensers,id'],
            'dispenser_readings.*.product_id' => ['required', 'exists:products,id'],
            'dispenser_readings.*.start_reading' => ['required', 'numeric', 'min:0'],
            'dispenser_readings.*.end_reading' => ['required', 'numeric', 'gte:dispenser_readings.*.start_reading'],
            'dispenser_readings.*.meter_test' => ['nullable', 'numeric', 'min:0'],
            'dispenser_readings.*.item_rate' => ['required', 'numeric', 'min:0'],
            'dispenser_readings.*.reading_by' => ['nullable', 'exists:employees,id'],
            'other_product_sales' => ['nullable', 'array'],
            'other_product_sales.*.product_id' => ['required', 'exists:products,id'],
            'other_product_sales.*.quantity' => ['required', 'numeric', 'gt:0'],
            'other_product_sales.*.unit_price' => ['required', 'numeric', 'min:0'],
            'other_product_sales.*.employee_id' => ['required', 'exists:employees,id'],
            'credit_sales' => ['required', 'numeric', 'min:0'],
            'bank_sales' => ['required', 'numeric', 'min:0'],
            'cash_sales' => ['required', 'numeric', 'min:0'],
            'credit_sales_other' => ['required', 'numeric', 'min:0'],
            'bank_sales_other' => ['required', 'numeric', 'min:0'],
            'cash_sales_other' => ['required', 'numeric', 'min:0'],
            'cash_receive' => ['required', 'numeric', 'min:0'],
            'bank_receive' => ['nullable', 'numeric', 'min:0'],
            'total_cash' => ['required', 'numeric', 'min:0'],
            'cash_payment' => ['required', 'numeric', 'min:0'],
            'bank_payment' => ['nullable', 'numeric', 'min:0'],
            'office_payment' => ['required', 'numeric', 'min:0'],
            'final_due_amount' => ['required', 'numeric'],
            'remarks' => ['nullable', 'string'],
        ]);

        $this->closings->close($validated);

        return back()->with('success', 'Shift closed successfully.');
    }
}
