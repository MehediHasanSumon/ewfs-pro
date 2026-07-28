<?php

namespace App\Http\Controllers;

use App\Helpers\NumberToWordsHelper;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Vehicle;
use App\Services\SalePostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class SaleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SalePostingService $sales
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index', 'downloadBatchPdf']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf']),
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
            'vehicles' => Vehicle::query()
                ->where('status', true)
                ->whereHas('customer', fn ($query) => $query->active())
                ->with([
                    'customer:id,name',
                    'products' => fn ($query) => $query
                        ->where('products.status', true)
                        ->select('products.id', 'products.product_name'),
                ])
                ->get(['id', 'vehicle_number', 'customer_id']),
            'salesHistory' => Sale::query()
                ->with('items:id,sale_id,product_id')
                ->where('sale_type', 'regular')
                ->whereHas('journalEntry', fn ($query) => $query->posted())
                ->whereNotNull('vehicle_number_snapshot')
                ->latest('id')
                ->get(['id', 'vehicle_number_snapshot', 'customer_name_snapshot'])
                ->unique('vehicle_number_snapshot')
                ->map(fn (Sale $sale) => [
                    'vehicle_no' => $sale->vehicle_number_snapshot,
                    'customer' => $sale->customer_name_snapshot,
                    'product_id' => $sale->product_id,
                ])
                ->values(),
            'products' => Product::query()
                ->with(['unit', 'stock', 'activeRate'])
                ->active()
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
            'uniqueVehicles' => Sale::query()
                ->where('sale_type', 'regular')
                ->whereNotNull('vehicle_number_snapshot')
                ->distinct()
                ->pluck('vehicle_number_snapshot'),
            'filters' => $request->only([
                'search', 'customer', 'payment_status', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());
        $this->sales->createMany($validated);

        return back()->with('success', 'Sale created successfully.');
    }

    public function edit(Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);

        return response()->json([
            'sale' => $sale->load([
                'items.category',
                'items.unit',
                'shift',
                'transaction',
            ]),
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        abort_unless($sale->sale_type === 'regular', 404);

        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'customer' => ['required', 'string', 'max:150'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'vehicle_no' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'exists:products,id'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'invoice_no' => ['required', 'string', 'max:100'],
            'memo_no' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['required', 'in:Cash,Bank,Mobile Bank'],
            'to_account_id' => ['required', 'exists:accounts,id'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string'],
            'branch_name' => ['nullable', 'string'],
            'account_no' => ['nullable', 'string'],
            'bank_type' => ['nullable', 'string'],
            'cheque_no' => ['nullable', 'string'],
            'cheque_date' => ['nullable', 'date'],
            'mobile_bank' => ['nullable', 'string'],
        ]);

        $this->sales->replace($sale, $validated, $validated);

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

        Sale::query()
            ->where('sale_type', 'regular')
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Sale $sale) => $this->sales->reverse($sale));

        return back()->with('success', 'Sales deleted successfully.');
    }

    public function downloadBatchPdf(string $batchCode)
    {
        $sales = Sale::query()
            ->with(['items.product.unit', 'shift'])
            ->whereHas('batch', fn ($query) => $query->where('batch_code', $batchCode))
            ->whereHas('journalEntry', fn ($query) => $query->posted())
            ->get();

        abort_if($sales->isEmpty(), 404, 'Batch not found');

        $customerGroups = $sales->groupBy('customer');
        foreach ($customerGroups as $customer => $customerSales) {
            $customerGroups[$customer]->totalInWords = NumberToWordsHelper::convert(
                floor($customerSales->sum('total_amount'))
            );
        }

        return Pdf::loadView('pdf.batch-invoice', [
            'customerGroups' => $customerGroups,
            'batchCode' => $batchCode,
            'companySetting' => CompanySetting::query()->first(),
        ])->setPaper('A4', 'portrait')->stream("batch-invoice-{$batchCode}.pdf");
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
            ->with(['items.category', 'items.unit', 'shift', 'transaction', 'batch'])
            ->where('sale_type', 'regular')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhere('customer_name_snapshot', 'like', "%{$search}%"));
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

    private function storeRules(): array
    {
        return [
            'sale_date' => ['required', 'date'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.customer' => ['required', 'string', 'max:150'],
            'products.*.mobile_number' => ['nullable', 'string', 'max:50'],
            'products.*.vehicle_no' => ['required', 'string', 'max:50'],
            'products.*.memo_no' => ['nullable', 'string', 'max:150'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.amount' => ['required', 'numeric', 'min:0'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'products.*.payment_type' => ['required', 'in:Cash,Bank,Mobile Bank'],
            'products.*.to_account_id' => ['required', 'exists:accounts,id'],
            'products.*.paid_amount' => ['required', 'numeric', 'min:0'],
            'products.*.remarks' => ['nullable', 'string'],
            'products.*.bank_name' => ['nullable', 'string'],
            'products.*.branch_name' => ['nullable', 'string'],
            'products.*.account_no' => ['nullable', 'string'],
            'products.*.bank_type' => ['nullable', 'string'],
            'products.*.cheque_no' => ['nullable', 'string'],
            'products.*.cheque_date' => ['nullable', 'date'],
            'products.*.mobile_bank' => ['nullable', 'string'],
        ];
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
