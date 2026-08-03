<?php

namespace App\Http\Controllers;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\VoucherTransactionTypeOptionsRequest;
use App\Http\Requests\VoucherTransactionTypeRequest;
use App\Http\Resources\VoucherTransactionTypeResource;
use App\Models\CompanySetting;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use App\Services\VoucherTransactionTypeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class VoucherTransactionTypeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VoucherTransactionTypeService $transactionTypes
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:voucher-transaction-type-view', only: ['index', 'downloadPdf']),
            new Middleware(
                'permission:view-voucher|create-voucher|update-voucher|voucher-transaction-type-view',
                only: ['options']
            ),
            new Middleware('permission:voucher-transaction-type-create', only: ['store']),
            new Middleware('permission:voucher-transaction-type-update', only: ['edit', 'update']),
            new Middleware('permission:voucher-transaction-type-delete', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $transactionTypes = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        $transactionTypes->through(
            fn (VoucherTransactionType $transactionType) => (new VoucherTransactionTypeResource(
                $transactionType
            ))->resolve($request)
        );

        return Inertia::render('VoucherTransactionTypes/Index', [
            'voucherTransactionTypes' => $transactionTypes,
            'voucherCategories' => VoucherCategory::query()
                ->where('status', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'voucherTypes' => VoucherTransactionTypeHelper::voucherTypes(),
            'filters' => $request->only([
                'search',
                'category',
                'voucher_type',
                'status',
                'system',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function edit(VoucherTransactionType $voucherTransactionType)
    {
        return response()->json([
            'paymentSubType' => [
                ...$voucherTransactionType->only([
                    'id',
                    'code',
                    'name',
                    'voucher_category_id',
                    'status',
                ]),
                'type' => $voucherTransactionType->voucher_type,
            ],
            'voucherTransactionType' => new VoucherTransactionTypeResource(
                $voucherTransactionType->load('voucherCategory:id,code,name')
            ),
        ]);
    }

    public function options(VoucherTransactionTypeOptionsRequest $request)
    {
        $validated = $request->validated();

        return VoucherTransactionTypeResource::collection(
            $this->transactionTypes->options(
                (int) $validated['category_id'],
                $validated['voucher_type'],
                isset($validated['selected_id'])
                    ? (int) $validated['selected_id']
                    : null
            )
        );
    }

    public function store(VoucherTransactionTypeRequest $request)
    {
        $this->transactionTypes->create($request->validated());

        return back()->with('success', 'Voucher transaction type created successfully.');
    }

    public function update(
        VoucherTransactionTypeRequest $request,
        VoucherTransactionType $voucherTransactionType
    ) {
        $this->transactionTypes->update(
            $voucherTransactionType,
            $request->validated()
        );

        return back()->with('success', 'Voucher transaction type updated successfully.');
    }

    public function destroy(VoucherTransactionType $voucherTransactionType)
    {
        $this->transactionTypes->delete($voucherTransactionType);

        return back()->with('success', 'Voucher transaction type deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:voucher_transaction_types,id'],
        ]);

        $deleted = $this->transactionTypes->deleteMany($validated['ids']);

        return back()->with(
            'success',
            "{$deleted} voucher transaction types deleted successfully."
        );
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.voucher-transaction-types', [
            'voucherTransactionTypes' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('voucher-transaction-types.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('voucher_category_id', $request->integer('category'));
        }

        if ($request->filled('voucher_type') && $request->input('voucher_type') !== 'all') {
            $query->where('voucher_type', $request->input('voucher_type'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('system') && $request->input('system') !== 'all') {
            $query->where('is_system', $request->input('system') === 'system');
        }

        $allowedSorts = [
            'code',
            'name',
            'voucher_category_id',
            'voucher_type',
            'sort_order',
            'status',
            'is_system',
            'created_at',
        ];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'sort_order';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('voucher_category_id')
            ->orderBy('id');
    }
}
