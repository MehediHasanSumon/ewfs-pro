<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\PaymentSubType;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Services\VoucherPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class ReceivedVoucherController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VoucherPostingService $vouchers
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-voucher', only: ['index']),
            new Middleware('permission:view-voucher|can-voucher-download', only: ['downloadPdf']),
            new Middleware('permission:create-voucher', only: ['store']),
            new Middleware('permission:update-voucher', only: ['update']),
            new Middleware('permission:delete-voucher', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $accounts = Account::query()
            ->with('group:id,name')
            ->active()
            ->get(['id', 'name', 'ac_number', 'group_id']);

        return Inertia::render('Vouchers/ReceivedVoucher', [
            'vouchers' => $this->filteredQuery($request)
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'accounts' => $accounts,
            'groupedAccounts' => $accounts->groupBy(fn (Account $account) => $account->group?->name ?? 'Other'),
            'shifts' => Shift::query()->where('status', true)->get(['id', 'name']),
            'closedShifts' => ShiftClosing::query()
                ->where('status', 'posted')
                ->get(['business_date', 'shift_id'])
                ->map(fn (ShiftClosing $closing) => [
                    'close_date' => $closing->business_date->format('Y-m-d'),
                    'shift_id' => $closing->shift_id,
                ]),
            'voucherCategories' => VoucherCategory::query()->where('status', true)->get(),
            'paymentSubTypes' => PaymentSubType::query()
                ->with('voucherCategory')
                ->where('status', true)
                ->whereIn('type', ['receipt', 'both'])
                ->get(),
            'filters' => $request->only([
                'search', 'shift', 'payment_method', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->batchRules());
        $this->vouchers->createMany('receipt', $validated);

        return back()->with('success', 'Received voucher created successfully.');
    }

    public function update(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->voucher_type === 'receipt', 404);
        $validated = $request->validate($this->singleRules());
        $this->vouchers->replace($voucher, $validated);

        return back()->with('success', 'Received voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        abort_unless($voucher->voucher_type === 'receipt', 404);
        $this->vouchers->reverse($voucher);

        return back()->with('success', 'Received voucher deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:vouchers,id'],
        ]);

        Voucher::query()
            ->where('voucher_type', 'receipt')
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Voucher $voucher) => $this->vouchers->reverse($voucher));

        return back()->with('success', 'Received vouchers deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.received-vouchers', [
            'vouchers' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('received-vouchers.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = Voucher::query()
            ->with([
                'fromAccount',
                'toAccount',
                'shift',
                'voucherCategory',
                'paymentSubType',
                'lines.paymentDetail',
                'transaction',
            ])
            ->where('voucher_type', 'receipt')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('voucher_no', 'like', "%{$search}%")
                ->orWhereHas('lines.account', fn ($account) => $account->where('name', 'like', "%{$search}%")));
        }

        if ($request->filled('shift') && $request->shift !== 'all') {
            $query->whereHas('shift', fn ($shift) => $shift->where('name', $request->shift));
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $method = match ($request->payment_method) {
                'Bank' => 'bank',
                'Mobile Bank' => 'mobile_bank',
                default => 'cash',
            };
            $query->whereHas('lines.paymentDetail', fn ($detail) => $detail->where('payment_method', $method));
        }

        $query
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('voucher_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('voucher_date', '<=', $date));

        $sort = in_array($request->sort_by, ['id', 'voucher_date', 'voucher_no', 'created_at'], true)
            ? $request->sort_by
            : 'created_at';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function batchRules(): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'vouchers' => ['required', 'array', 'min:1'],
        ];

        foreach ($this->lineRules() as $field => $rule) {
            $rules['vouchers.*.'.$field] = $rule;
        }

        return $rules;
    }

    private function singleRules(): array
    {
        return ['date' => ['required', 'date'], 'shift_id' => ['nullable', 'exists:shifts,id']]
            + $this->lineRules();
    }

    private function lineRules(): array
    {
        return [
            'voucher_category_id' => ['required', 'exists:voucher_categories,id'],
            'payment_sub_type_id' => ['required', 'exists:payment_sub_types,id'],
            'from_account_id' => ['required', 'different:to_account_id', 'exists:accounts,id'],
            'to_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:Cash,Bank,Mobile Bank'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string'],
            'branch_name' => ['nullable', 'string'],
            'account_no' => ['nullable', 'string'],
            'bank_type' => ['nullable', 'string'],
            'cheque_no' => ['nullable', 'string'],
            'cheque_date' => ['nullable', 'date'],
            'mobile_bank' => ['nullable', 'string'],
            'mobile_number' => ['nullable', 'string'],
        ];
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
