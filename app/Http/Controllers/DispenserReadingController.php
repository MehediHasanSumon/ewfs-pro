<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispenserCalculationRequest;
use App\Http\Requests\ShiftClosingRequest;
use App\Models\Account;
use App\Models\CreditSaleCustomer;
use App\Models\Customer;
use App\Models\Dispenser;
use App\Models\DispenserReading;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Vehicle;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use App\Services\DispenserCalculationService;
use App\Services\ShiftClosingService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class DispenserReadingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ShiftClosingService $closings,
        private readonly DispenserCalculationService $calculations
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-dispenser', only: [
                'index',
                'getShiftsByDate',
                'getShiftClosingData',
                'calculateOtherProducts',
            ]),
            new Middleware('permission:create-dispenser', only: ['store']),
        ];
    }

    public function index()
    {
        $dispenserReading = Dispenser::query()
            ->with(['product.activeRate', 'latestReading'])
            ->where('status', true)
            ->whereHas(
                'product',
                fn ($product) => $product->allowedForDispenser()
            )
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
            ->allowedForDispenser()
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
            'otherProducts' => [],
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
            'voucherTransactionTypes' => VoucherTransactionType::query()
                ->with('voucherCategory')
                ->where('status', true)
                ->orderBy('sort_order')
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
        $calculation = $this->calculations->calculateForShift(
            $date,
            $shift
        );
        $operational = $this->closings->operationalSummary(
            $date,
            $shift,
            $calculation['summary']
        );

        return response()->json($operational + [
            'otherProducts' => $calculation['products'],
            'otherProductsSummary' => $calculation['summary'],
        ]);
    }

    public function calculateOtherProducts(DispenserCalculationRequest $request)
    {
        $validated = $request->validated();

        return response()->json($this->calculations->calculateForShift(
            $validated['transaction_date'],
            (int) $validated['shift_id'],
            $validated['other_product_sales'] ?? []
        ));
    }

    public function store(ShiftClosingRequest $request)
    {
        $validated = $request->validated();

        $this->closings->close($validated);

        return back()->with('success', 'Shift closed successfully.');
    }
}
