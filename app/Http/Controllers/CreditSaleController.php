<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Services\CreditSalePostingService;
use App\Services\VehicleSalesContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CreditSaleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CreditSalePostingService $creditSales,
        private readonly VehicleSalesContextService $vehicleSalesContext
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-credit-sale', only: ['index']),
            new Middleware('permission:view-credit-sale|can-credit-sale-download', only: ['downloadPdf']),
            new Middleware('permission:create-credit-sale', only: ['store']),
            new Middleware('permission:update-credit-sale', only: ['edit', 'update']),
            new Middleware('permission:delete-credit-sale', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        return Inertia::render('CreditSales/Index', [
            'creditSales' => $this->filteredQuery($request)
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'vehicles' => $this->vehicleSalesContext->forSalesSelection(),
            'customers' => Customer::query()->active()->get(['id', 'name']),
            'products' => Product::query()
                ->with('activeRate')
                ->active()
                ->get(['id', 'product_name'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'sales_price' => (float) ($product->activeRate?->sales_price ?? 0),
                ]),
            'shifts' => Shift::query()->where('status', true)->get(['id', 'name']),
            'closedShifts' => ShiftClosing::query()
                ->where('status', 'posted')
                ->get(['business_date', 'shift_id'])
                ->map(fn (ShiftClosing $closing) => [
                    'close_date' => $closing->business_date->format('Y-m-d'),
                    'shift_id' => $closing->shift_id,
                ]),
            'filters' => $request->only([
                'search', 'customer', 'payment_status', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());
        $this->creditSales->createMany($validated);

        return back()->with('success', 'Credit sale created successfully.');
    }

    public function edit(CreditSale $creditSale)
    {
        return response()->json([
            'creditSale' => $creditSale->load([
                'shift',
                'customers.customer',
                'customers.items.product',
                'customers.items.category',
                'customers.items.unit',
                'customers.items.vehicle',
            ]),
        ]);
    }

    public function update(Request $request, CreditSale $creditSale)
    {
        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'product_id' => ['required', 'exists:products,id'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'memo_no' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $this->creditSales->replace($creditSale, $validated, $validated);

        return back()->with('success', 'Credit sale updated successfully.');
    }

    public function destroy(CreditSale $creditSale)
    {
        $this->creditSales->reverse($creditSale);

        return back()->with('success', 'Credit sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:credit_sales,id'],
        ]);

        CreditSale::query()
            ->whereIn('id', $validated['ids'])
            ->with('customers.journalEntry')
            ->get()
            ->each(fn (CreditSale $sale) => $this->creditSales->reverse($sale));

        return back()->with('success', 'Credit sales deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.credit-sales', [
            'creditSales' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('credit-sales.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = CreditSale::query()
            ->with([
                'shift',
                'customer:id,name',
                'customers.customer',
                'customers.items.product',
                'customers.items.category',
                'customers.items.unit',
                'customers.items.vehicle',
                'customers.paymentAllocations',
            ])
            ->whereHas('customers.journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('customers.customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%")));
        }

        if ($request->filled('customer') && $request->customer !== 'all') {
            $query->whereHas('customers', fn ($allocation) => $allocation->where('customer_id', $request->customer));
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $allocationStatuses = match ($request->payment_status) {
                'paid' => ['paid'],
                'partial' => ['partially_paid'],
                'due' => ['open'],
                default => [],
            };
            $query->whereHas('customers', fn ($allocation) => $allocation->whereIn('status', $allocationStatuses));
        }

        $query
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('sale_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('sale_date', '<=', $date));

        $sort = in_array($request->sort_by, ['id', 'sale_date', 'invoice_no', 'grand_total', 'created_at'], true)
            ? $request->sort_by
            : 'created_at';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function storeRules(): array
    {
        return [
            'sale_date' => ['required', 'date'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'memo_no' => ['nullable', 'string', 'max:150'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.customer_id' => ['required', 'exists:customers,id'],
            'products.*.vehicle_id' => ['required', 'exists:vehicles,id'],
            'products.*.memo_no' => ['nullable', 'string', 'max:150'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.amount' => ['required', 'numeric', 'min:0'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'products.*.due_amount' => ['required', 'numeric', 'min:0'],
            'products.*.remarks' => ['nullable', 'string'],
        ];
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
