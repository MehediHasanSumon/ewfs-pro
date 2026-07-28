<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Group;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\DocumentNumberService;
use App\Services\PartyAccountService;
use App\Services\PartyLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SupplierController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PartyAccountService $partyAccounts,
        private readonly PartyLedgerService $partyLedger
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-supplier', only: ['index', 'show', 'statement']),
            new Middleware('permission:create-supplier', only: ['store']),
            new Middleware('permission:update-supplier', only: ['update']),
            new Middleware('permission:delete-supplier', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-supplier-download', only: ['downloadPdf', 'downloadPurchasesPdf', 'downloadPaymentsPdf']),
        ];
    }

    public function index(Request $request)
    {
        $query = Supplier::query()->with('account.group:id,code,name');

        if ($request->search) {
            $query->where(function ($builder) use ($request) {
                $builder->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('mobile', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        [$sortBy, $sortOrder] = $this->sorting($request);
        $query->orderBy($sortBy, $sortOrder);

        $suppliers = $query
            ->paginate(max(1, min((int) $request->get('per_page', 10), 100)))
            ->withQueryString();
        $metrics = $this->partyLedger->supplierMetrics($suppliers->getCollection());
        $suppliers->setCollection(
            $suppliers->getCollection()->map(function (Supplier $supplier) use ($metrics) {
                $metric = $metrics->get($supplier->id);

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'mobile' => $supplier->mobile,
                    'email' => $supplier->email,
                    'address' => $supplier->address,
                    'proprietor_name' => $supplier->proprietor_name,
                    'group_id' => $supplier->account?->group_id,
                    'group_code' => $supplier->account?->group?->code,
                    'account_number' => $supplier->account?->ac_number,
                    'total_purchases' => $metric['total_purchases'],
                    'total_payment' => $metric['total_paid'],
                    'total_due' => $metric['current_due'],
                    'status' => $supplier->status,
                    'created_at' => $supplier->created_at->format('Y-m-d'),
                ];
            })
        );

        $lastSupplier = Supplier::query()
            ->with('account.group:id,code')
            ->latest('id')
            ->first();
        $lastSupplierGroup = $lastSupplier?->account?->group
            ? [
                'id' => $lastSupplier->account->group->id,
                'code' => $lastSupplier->account->group->code,
            ]
            : null;

        return Inertia::render('Suppliers/Suppliers', [
            'suppliers' => $suppliers,
            'groups' => Group::query()->active()->get(['id', 'code', 'name']),
            'lastSupplierGroup' => $lastSupplierGroup,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request) {
            $status = $request->boolean('status', true);
            $account = $this->partyAccounts->createSupplierAccount($validated['name'], $status);

            Supplier::query()->create([
                'account_id' => $account->id,
                'code' => $this->numbers->next('supplier', 'SUP', null, 4),
                'name' => $validated['name'],
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'proprietor_name' => $validated['proprietor_name'] ?? null,
                'status' => $status,
            ]);
        });

        return redirect()->back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request, $supplier) {
            $status = $request->boolean('status', true);
            $supplier->loadMissing('account');
            $supplier->account?->update([
                'name' => $validated['name'],
                'status' => $status,
            ]);
            $supplier->update([
                'name' => $validated['name'],
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'proprietor_name' => $validated['proprietor_name'] ?? null,
                'status' => $status,
            ]);
        });

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('account:id,name,ac_number');
        $recentPurchases = $supplier->purchases()
            ->posted()
            ->with(['paymentAllocations', 'journalEntry.lines'])
            ->latest('purchase_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Purchase $purchase) => $this->purchaseRow($purchase));

        $paymentQuery = $this->partyLedger->vouchers(
            'supplier_id',
            $supplier->id,
            'payment'
        );
        $paymentCount = (clone $paymentQuery)->count();
        $recentPayments = $this->partyLedger->voucherRows(
            $paymentQuery
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'Paid'
        );
        $metric = $this->partyLedger
            ->supplierMetrics(collect([$supplier]))
            ->get($supplier->id);

        return Inertia::render('Suppliers/SupplierDetails', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'mobile' => $supplier->mobile,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'proprietor_name' => $supplier->proprietor_name,
                'status' => $supplier->status,
                'created_at' => $supplier->created_at->format('Y-m-d'),
                'account' => $supplier->account,
            ],
            'recentPurchases' => $recentPurchases,
            'recentPayments' => $recentPayments,
            'totalPurchases' => $metric['total_purchases'],
            'purchaseCount' => $metric['purchase_count'],
            'totalPaid' => $metric['total_paid'],
            'paymentCount' => $paymentCount,
            'currentDue' => $metric['current_due'],
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $this->deleteSupplier($supplier);

        return redirect()->back()->with('success', 'Supplier deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:suppliers,id'],
        ]);

        $suppliers = Supplier::query()
            ->whereIn('id', $request->ids)
            ->with('account')
            ->get();

        DB::transaction(function () use ($suppliers) {
            foreach ($suppliers as $supplier) {
                $this->assertSupplierCanBeDeleted($supplier);
            }

            foreach ($suppliers as $supplier) {
                $account = $supplier->account;
                $supplier->delete();
                $account?->delete();
            }
        });

        return redirect()->back()->with(
            'success',
            count($request->ids).' suppliers deleted successfully.'
        );
    }

    public function statement(Request $request, Supplier $supplier)
    {
        $supplier->load('account:id,name,ac_number');
        $metric = $this->partyLedger
            ->supplierMetrics(collect([$supplier]))
            ->get($supplier->id);

        $purchaseQuery = $supplier->purchases()
            ->posted()
            ->with(['paymentAllocations', 'journalEntry.lines']);
        $this->applyDateFilter(
            $purchaseQuery,
            'purchase_date',
            $request->start_date,
            $request->end_date
        );

        $allPurchases = $purchaseQuery
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Purchase $purchase) => $this->purchaseRow($purchase));

        $recentPayments = $this->partyLedger->paginatedVoucherRows(
            $this->partyLedger->vouchers(
                'supplier_id',
                $supplier->id,
                'payment',
                $request->start_date,
                $request->end_date
            ),
            10,
            'Paid'
        );

        return Inertia::render('Suppliers/SupplierStatement', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'mobile' => $supplier->mobile,
                'address' => $supplier->address,
                'account' => $supplier->account,
            ],
            'transactions' => $this->partyLedger->statement($supplier->account, 'supplier'),
            'currentBalance' => $metric['current_due'],
            'allPurchases' => $allPurchases,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $query = Supplier::query()->with('account');

        if ($request->search) {
            $query->where(function ($builder) use ($request) {
                $builder->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('mobile', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        [$sortBy, $sortOrder] = $this->sorting($request);
        $suppliers = $query->orderBy($sortBy, $sortOrder)->get();
        $metrics = $this->partyLedger->supplierMetrics($suppliers);
        $suppliers->each(function (Supplier $supplier) use ($metrics) {
            $metric = $metrics->get($supplier->id);
            $supplier->setAttribute('total_purchases', $metric['total_purchases']);
            $supplier->setAttribute('total_payment', $metric['total_paid']);
            $supplier->setAttribute('total_due', $metric['current_due']);
        });

        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.suppliers', compact('suppliers', 'companySetting'));

        return $pdf->stream('suppliers.pdf');
    }

    public function downloadPurchasesPdf(Request $request, Supplier $supplier)
    {
        $supplier->load('account');
        $query = $supplier->purchases()
            ->posted()
            ->with(['paymentAllocations', 'journalEntry.lines']);
        $this->applyDateFilter(
            $query,
            'purchase_date',
            $request->start_date,
            $request->end_date
        );
        $purchases = $query
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Purchase $purchase) => $this->purchaseRow($purchase));

        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView(
            'pdf.supplier-purchases',
            compact('supplier', 'purchases', 'companySetting')
        );

        return $pdf->stream('supplier-purchases.pdf');
    }

    public function downloadPaymentsPdf(Request $request, Supplier $supplier)
    {
        $supplier->load('account');
        $payments = $this->partyLedger->voucherRows(
            $this->partyLedger->vouchers(
                'supplier_id',
                $supplier->id,
                'payment',
                $request->start_date,
                $request->end_date
            )
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            'Paid'
        );

        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView(
            'pdf.supplier-payments',
            compact('supplier', 'payments', 'companySetting')
        );

        return $pdf->stream('supplier-payments.pdf');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'proprietor_name' => ['nullable', 'string', 'max:255'],
            'status' => ['boolean'],
        ];
    }

    private function sorting(Request $request): array
    {
        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'name', 'mobile', 'email', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';

        return [$sortBy, $request->get('sort_order') === 'asc' ? 'asc' : 'desc'];
    }

    private function purchaseRow(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'date' => $purchase->purchase_date->format('Y-m-d'),
            'invoice_no' => $purchase->invoice_no,
            'total' => (float) $purchase->grand_total,
            'total_amount' => (float) $purchase->grand_total,
            'paid' => (float) $purchase->paid_amount,
            'paid_amount' => (float) $purchase->paid_amount,
            'due' => (float) $purchase->due_amount,
            'due_amount' => (float) $purchase->due_amount,
            'status' => $purchase->status,
        ];
    }

    private function applyDateFilter(
        $query,
        string $column,
        ?string $startDate,
        ?string $endDate
    ): void {
        if ($startDate) {
            $query->whereDate($column, '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate($column, '<=', $endDate);
        }
    }

    private function deleteSupplier(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            $supplier->loadMissing('account');
            $this->assertSupplierCanBeDeleted($supplier);
            $account = $supplier->account;
            $supplier->delete();
            $account?->delete();
        });
    }

    private function assertSupplierCanBeDeleted(Supplier $supplier): void
    {
        if ($supplier->journalLines()->exists() || $supplier->purchases()->exists()) {
            throw ValidationException::withMessages([
                'supplier' => 'This supplier has financial records and cannot be deleted.',
            ]);
        }
    }
}
