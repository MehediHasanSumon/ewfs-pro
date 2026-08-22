<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\SalePostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class WhiteSaleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SalePostingService $sales
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-white-sale', only: ['index', 'show', 'getCustomerByMobile']),
            new Middleware('permission:view-white-sale|can-white-sale-download', only: ['downloadSinglePdf', 'downloadPdf']),
            new Middleware('permission:create-white-sale', only: ['create', 'store']),
            new Middleware('permission:update-white-sale', only: ['edit', 'update']),
            new Middleware('permission:delete-white-sale', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        return Inertia::render('WhiteSales/Index', [
            'whiteSales' => $this->filteredQuery($request)
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'shifts' => Shift::query()->where('status', true)->get(),
            'products' => Product::query()
                ->with(['category', 'unit', 'activeRate', 'rates'])
                ->active()
                ->get(),
            'filters' => $request->only([
                'search', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function create()
    {
        return redirect()->route('white-sales.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->sales->createWhiteSale($validated);

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale created successfully.');
    }

    public function show(Sale $whiteSale)
    {
        $this->assertWhiteSale($whiteSale);

        return response()->json([
            'whiteSale' => $whiteSale->load([
                'shift',
                'products.product',
                'products.category',
                'products.unit',
            ]),
        ]);
    }

    public function edit(Sale $whiteSale)
    {
        return $this->show($whiteSale);
    }

    public function update(Request $request, Sale $whiteSale)
    {
        $this->assertWhiteSale($whiteSale);
        $validated = $request->validate($this->rules());
        $this->sales->replaceWhiteSale($whiteSale, $validated);

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale updated successfully.');
    }

    public function getCustomerByMobile(string $mobile)
    {
        $customer = Sale::query()
            ->where('sale_type', 'white')
            ->where('customer_mobile_snapshot', $mobile)
            ->whereHas('journalEntry', fn ($entry) => $entry->posted())
            ->latest('id')
            ->first(['company_name_snapshot', 'proprietor_name_snapshot']);

        return response()->json($customer ? [
            'company_name' => $customer->company_name_snapshot,
            'proprietor_name' => $customer->proprietor_name_snapshot,
        ] : null);
    }

    public function destroy(Sale $whiteSale)
    {
        $this->assertWhiteSale($whiteSale);
        $this->sales->reverse($whiteSale);

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:sales,id'],
        ]);

        Sale::query()
            ->where('sale_type', 'white')
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Sale $sale) => $this->sales->reverse($sale));

        return redirect()->route('white-sales.index')
            ->with('success', 'Selected white sales deleted successfully.');
    }

    public function downloadSinglePdf(Sale $whiteSale)
    {
        $this->assertWhiteSale($whiteSale);
        $whiteSale->load(['shift', 'products.product', 'products.category', 'products.unit']);

        return Pdf::loadView('pdf.white-sale-invoice', [
            'whiteSale' => $whiteSale,
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('white-sale-'.$whiteSale->invoice_no.'.pdf');
    }

    public function downloadPosPdf(Sale $whiteSale)
    {
        $this->assertWhiteSale($whiteSale);
        $whiteSale->load(['shift', 'products.product', 'products.category', 'products.unit']);

        $itemCount = $whiteSale->products->count() ?: 1;
        $paperHeight = max(450, 340 + ($itemCount * 30));

        return Pdf::loadView('pdf.pos.white-sale-pos', [
            'whiteSale' => $whiteSale,
            'companySetting' => CompanySetting::query()->first(),
        ])->setPaper([0, 0, 226.77, $paperHeight], 'portrait')->stream('white-sale-pos-'.$whiteSale->invoice_no.'.pdf');
    }

    public function downloadPdf(Request $request)
    {
        $whiteSale = $this->filteredQuery($request)->firstOrFail();

        return $this->downloadSinglePdf($whiteSale);
    }

    private function filteredQuery(Request $request)
    {
        $query = Sale::query()
            ->with(['shift', 'products.product', 'products.category', 'products.unit'])
            ->where('sale_type', 'white')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhere('company_name_snapshot', 'like', "%{$search}%")
                ->orWhere('customer_mobile_snapshot', 'like', "%{$search}%"));
        }

        $query
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('sale_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('sale_date', '<=', $date));

        $sort = in_array($request->sort_by, ['id', 'sale_date', 'invoice_no', 'grand_total', 'created_at'], true)
            ? $request->sort_by
            : 'sale_date';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function rules(): array
    {
        return [
            'sale_date' => ['nullable', 'date'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'mobile_no' => ['required', 'string', 'max:50'],
            'company_name' => ['required', 'string', 'max:255'],
            'proprietor_name' => ['nullable', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product' => ['required', 'string', 'exists:products,product_name'],
            'products.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.amount' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
            'is_send_sms' => ['sometimes', 'boolean'],
        ];
    }

    private function assertWhiteSale(Sale $sale): void
    {
        abort_unless($sale->sale_type === 'white', 404);
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
