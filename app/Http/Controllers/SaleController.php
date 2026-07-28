<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerMobileLookupRequest;
use App\Http\Requests\SaleRequest;
use App\Http\Resources\SaleEditResource;
use App\Http\Resources\SalesCustomerLookupResource;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Services\SalePostingService;
use App\Services\SalesCustomerService;
use App\Services\VehicleSalesContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SalePostingService $sales,
        private readonly VehicleSalesContextService $vehicleSalesContext,
        private readonly SalesCustomerService $customers
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index']),
            new Middleware('permission:view-sale|create-sale', only: ['customerLookup']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf', 'downloadInvoice']),
            new Middleware('permission:create-sale', only: ['store']),
            new Middleware('permission:update-sale', only: ['edit', 'update']),
            new Middleware('permission:delete-sale', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);
        $sales = $query->paginate($this->perPage($request))->withQueryString();
        $accounts = Account::query()
            ->with('group:id,name')
            ->active()
            ->get(['id', 'name', 'ac_number', 'group_id']);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'accounts' => $accounts,
            'groupedAccounts' => $accounts->groupBy(fn (Account $account) => $account->group?->name ?? 'Other'),
            'vehicles' => $this->vehicleSalesContext->forPosSelection(),
            'products' => Product::query()
                ->with(['unit', 'stock', 'activeRate'])
                ->active()
                ->orderBy('product_name')
                ->get(['id', 'product_name', 'product_code', 'unit_id'])
                ->each(fn (Product $product) => $product->setAttribute(
                    'sales_price',
                    (float) ($product->activeRate?->sales_price ?? 0)
                )),
            'shifts' => Shift::query()->where('status', true)->get(['id', 'name']),
            'closedShifts' => $this->closedShifts(),
            'uniqueCustomers' => Sale::query()
                ->where('sale_type', 'regular')
                ->whereNotNull('customer_name_snapshot')
                ->distinct()
                ->pluck('customer_name_snapshot'),
            'filters' => $request->only([
                'search', 'customer', 'payment_status', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function customerLookup(CustomerMobileLookupRequest $request)
    {
        $customer = $this->customers->lookup($request->validated('mobile'));

        return $customer
            ? new SalesCustomerLookupResource($customer)
            : response()->json(['data' => null]);
    }

    public function store(SaleRequest $request)
    {
        $this->sales->create($request->validated());

        return back()->with('success', 'Sale created successfully.');
    }

    public function edit(Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);
        abort_unless($sale->journalEntry?->status === 'posted', 404);

        $sale->load([
            'items.product',
            'items.category',
            'items.unit',
            'paymentDetail',
            'transaction.account',
        ]);

        return response()->json([
            'sale' => (new SaleEditResource($sale))->resolve(),
        ]);
    }

    public function update(SaleRequest $request, Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);
        abort_unless($sale->journalEntry?->status === 'posted', 404);

        $this->sales->replace($sale, $request->validated());

        return back()->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);
        $this->sales->reverse($sale);

        return back()->with('success', 'Sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:sales,id'],
        ]);

        DB::transaction(function () use ($validated) {
            Sale::query()
                ->where('sale_type', 'regular')
                ->whereIn('id', $validated['ids'])
                ->with('journalEntry')
                ->lockForUpdate()
                ->get()
                ->each(fn (Sale $sale) => $this->sales->reverse($sale));
        });

        return back()->with('success', 'Sales deleted successfully.');
    }

    public function downloadInvoice(Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);
        abort_unless($sale->journalEntry?->status === 'posted', 404);

        return Pdf::loadView('pdf.sale-invoice', [
            'sale' => $sale->load([
                'items.product',
                'items.unit',
                'shift',
                'paymentDetail.account',
                'transaction.account',
            ]),
            'companySetting' => CompanySetting::query()->first(),
        ])->setPaper('A4', 'portrait')->stream("sale-invoice-{$sale->invoice_no}.pdf");
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.sales', [
            'sales' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('sales.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = Sale::query()
            ->with([
                'items.product',
                'items.category',
                'items.unit',
                'shift',
                'transaction',
                'paymentDetail',
            ])
            ->where('sale_type', 'regular')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                ->orWhere('customer_mobile_snapshot', 'like', "%{$search}%"));
        }

        if ($request->filled('customer') && $request->customer !== 'all') {
            $query->where('customer_name_snapshot', $request->customer);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $statuses = match ($request->payment_status) {
                'paid' => ['paid'],
                'partial' => ['partially_paid'],
                'due' => ['posted'],
                default => [],
            };
            $query->whereIn('status', $statuses);
        }

        $query
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('sale_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('sale_date', '<=', $date));

        $sort = in_array($request->sort_by, ['id', 'sale_date', 'invoice_no', 'grand_total', 'created_at'], true)
            ? $request->sort_by
            : 'created_at';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function closedShifts()
    {
        return ShiftClosing::query()
            ->where('status', 'posted')
            ->get(['business_date', 'shift_id'])
            ->map(fn (ShiftClosing $closing) => [
                'close_date' => $closing->business_date->format('Y-m-d'),
                'shift_id' => $closing->shift_id,
            ]);
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
