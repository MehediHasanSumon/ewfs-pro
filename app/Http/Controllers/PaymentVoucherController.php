<?php

namespace App\Http\Controllers;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\PaymentVoucherRequest;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use App\Services\VoucherPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PaymentVoucherController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VoucherPostingService $vouchers
    ) {}

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

        return Inertia::render('Vouchers/PaymentVoucher', [
            'vouchers' => $this->filteredQuery($request)
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'accounts' => $accounts,
            'groupedAccounts' => $accounts->groupBy(fn (Account $account) => $account->group?->name ?? 'Other'),
            'shifts' => Shift::query()->where('status', true)->get(['id', 'name']),
            'closedShifts' => $this->closedShifts(),
            'voucherCategories' => VoucherCategory::query()->where('status', true)->get(),
            'voucherTransactionTypes' => VoucherTransactionType::query()
                ->with('voucherCategory')
                ->where('status', true)
                ->whereIn('voucher_type', [
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    VoucherTransactionTypeHelper::bothVoucherType(),
                ])
                ->orderBy('sort_order')
                ->get(),
            'filters' => $request->only([
                'search', 'payment_method', 'start_date', 'end_date',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(PaymentVoucherRequest $request)
    {
        $this->vouchers->createMany('payment', $request->validated());

        return back()->with('success', 'Payment voucher created successfully.');
    }

    public function update(PaymentVoucherRequest $request, Voucher $voucher)
    {
        abort_unless($voucher->voucher_type === 'payment', 404);
        $this->vouchers->replace($voucher, $request->validated());

        return back()->with('success', 'Payment voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        abort_unless($voucher->voucher_type === 'payment', 404);
        $this->vouchers->reverse($voucher);

        return back()->with('success', 'Payment voucher deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:vouchers,id'],
        ]);

        Voucher::query()
            ->where('voucher_type', 'payment')
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Voucher $voucher) => $this->vouchers->reverse($voucher));

        return back()->with('success', 'Payment vouchers deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.payment-vouchers', [
            'vouchers' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('payment-vouchers.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = Voucher::query()
            ->with([
                'fromAccount',
                'toAccount',
                'shift',
                'voucherCategory',
                'voucherTransactionType',
                'lines.paymentDetail',
                'transaction',
            ])
            ->where('voucher_type', 'payment')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($nested) => $nested
                ->where('voucher_no', 'like', "%{$search}%")
                ->orWhereHas('lines.account', fn ($account) => $account->where('name', 'like', "%{$search}%")));
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $method = $this->normalizeFilterMethod($request->payment_method);
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

    private function normalizeFilterMethod(string $method): string
    {
        return match ($method) {
            'Bank' => 'bank',
            'Mobile Bank' => 'mobile_bank',
            default => 'cash',
        };
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
