<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchasePostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PurchasePostingService $purchases
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-purchase', only: ['index']),
            new Middleware('permission:view-purchase|can-purchase-download', only: ['downloadPdf']),
            new Middleware('permission:create-purchase', only: ['store']),
            new Middleware('permission:update-purchase', only: ['edit', 'update']),
            new Middleware('permission:delete-purchase', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $purchases = $this->filteredQuery($request)
            ->paginate($this->perPage($request))
            ->withQueryString();
        $accounts = Account::query()
            ->with('group:id,code,name')
            ->active()
            ->get(['id', 'name', 'ac_number', 'group_id']);
        $groupedAccounts = $accounts->groupBy(
            fn (Account $account) => $account->group?->name ?? 'Other'
        );

        foreach (
            config('erp.accounting.payment_groups', []) as $paymentType => $groupCodes
        ) {
            $groupedAccounts->put(
                $paymentType,
                $accounts
                    ->filter(fn (Account $account) => in_array(
                        $account->group?->code,
                        $groupCodes,
                        true
                    ))
                    ->values()
            );
        }

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::query()->active()->get(['id', 'name']),
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'products' => Product::query()
                ->with(['stock', 'activeRate'])
                ->active()
                ->get(['id', 'product_name'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'purchase_price' => (float) ($product->activeRate?->purchase_price ?? 0),
                    'stock' => $product->stock ? [
                        'current_stock' => (float) $product->stock->current_stock,
                        'available_stock' => (float) $product->stock->available_stock,
                    ] : null,
                ]),
            'filters' => $request->only([
                'search', 'supplier', 'payment_status', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(PurchaseRequest $request)
    {
        $this->purchases->createMany($request->validated());

        return back()->with('success', 'Purchase created successfully.');
    }

    public function edit(Purchase $purchase)
    {
        return response()->json([
            'purchase' => $purchase->load([
                'supplier',
                'items.product',
                'items.unit',
                'journalEntry.lines.account',
                'transaction',
                'fromAccount',
            ]),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase)
    {
        $validated = $request->validated();
        $validated['shift_id'] = $validated['shift_id']
            ?? $purchase->shift_id;

        $this->purchases->replace(
            $purchase,
            $validated,
            $validated['products'][0]
        );

        return back()->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase)
    {
        $this->purchases->reverse($purchase);

        return back()->with('success', 'Purchase deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:purchases,id'],
        ]);

        Purchase::query()
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Purchase $purchase) => $this->purchases->reverse($purchase));

        return back()->with('success', 'Purchases deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.purchases', [
            'purchases' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('purchases.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = Purchase::query()
            ->with([
                'supplier',
                'items.product',
                'items.unit',
                'journalEntry.lines.account',
                'transaction',
                'fromAccount',
            ])
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%")));
        }

        if ($request->filled('supplier') && $request->supplier !== 'all') {
            $query->whereHas('supplier', fn ($supplier) => $supplier->where('name', $request->supplier));
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
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('purchase_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('purchase_date', '<=', $date));

        $sort = in_array($request->sort_by, ['id', 'purchase_date', 'invoice_no', 'grand_total', 'created_at'], true)
            ? $request->sort_by
            : 'created_at';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
