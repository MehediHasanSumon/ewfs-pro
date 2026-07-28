<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Group;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\Voucher;
use App\Services\VoucherPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class OfficePaymentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VoucherPostingService $vouchers
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-office-payment', only: ['index']),
            new Middleware('permission:view-office-payment|can-office-payment-download', only: ['downloadPdf']),
            new Middleware('permission:create-office-payment', only: ['store']),
            new Middleware('permission:update-office-payment', only: ['update']),
            new Middleware('permission:delete-office-payment', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $accounts = Account::query()
            ->with('group:id,name')
            ->active()
            ->get(['id', 'name', 'ac_number', 'group_id']);

        return Inertia::render('OfficePayments/Index', [
            'officePayments' => $this->filteredQuery($request)
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (Voucher $voucher) => $this->legacyShape($voucher)),
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
            'paymentTypes' => $this->paymentTypes(),
            'types' => [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'bank', 'label' => 'Bank'],
            ],
            'filters' => $request->only([
                'search', 'start_date', 'end_date', 'type', 'shift_id',
                'sort_by', 'sort_order', 'per_page',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->vouchers->createOfficePayment($validated);

        return back()->with('success', 'Office payment created successfully.');
    }

    public function update(Request $request, Voucher $officePayment)
    {
        abort_unless($officePayment->voucher_type === 'office_payment', 404);
        $validated = $request->validate($this->rules());
        $this->vouchers->replace($officePayment, $validated);

        return back()->with('success', 'Office payment updated successfully.');
    }

    public function destroy(Voucher $officePayment)
    {
        abort_unless($officePayment->voucher_type === 'office_payment', 404);
        $this->vouchers->reverse($officePayment);

        return back()->with('success', 'Office payment deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:vouchers,id'],
        ]);

        Voucher::query()
            ->where('voucher_type', 'office_payment')
            ->whereIn('id', $validated['ids'])
            ->with('journalEntry')
            ->get()
            ->each(fn (Voucher $voucher) => $this->vouchers->reverse($voucher));

        return back()->with('success', 'Office payments deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $officePayments = $this->filteredQuery($request)
            ->get()
            ->map(fn (Voucher $voucher) => $this->legacyShape($voucher));

        return Pdf::loadView('pdf.office-payments', [
            'officePayments' => $officePayments,
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('office-payments.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = Voucher::query()
            ->with([
                'shift',
                'fromAccount',
                'toAccount',
                'lines.paymentDetail',
                'transaction',
            ])
            ->where('voucher_type', 'office_payment')
            ->whereHas('journalEntry', fn ($entry) => $entry->posted());

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('fromAccount', fn ($account) => $account->where('name', 'like', "%{$search}%"));
        }

        $query
            ->when($request->start_date, fn ($q, $date) => $q->whereDate('voucher_date', '>=', $date))
            ->when($request->end_date, fn ($q, $date) => $q->whereDate('voucher_date', '<=', $date))
            ->when($request->shift_id, fn ($q, $shiftId) => $q->where('shift_id', $shiftId));

        if ($request->filled('type')) {
            $query->whereHas('lines.paymentDetail', fn ($detail) => $detail
                ->where('payment_method', $request->type === 'bank' ? 'bank' : 'cash'));
        }

        $sort = in_array($request->sort_by, ['id', 'voucher_date', 'voucher_no', 'created_at'], true)
            ? $request->sort_by
            : 'created_at';

        return $query->orderBy($sort, $request->sort_order === 'asc' ? 'asc' : 'desc');
    }

    private function legacyShape(Voucher $voucher): Voucher
    {
        $voucher->setRelation('to_account', $voucher->fromAccount);
        $voucher->setAttribute('payment_type', $voucher->payment_method ?? 'cash');
        $voucher->setAttribute('type', $voucher->payment_method ?? 'cash');

        return $voucher;
    }

    private function paymentTypes()
    {
        return Group::query()
            ->where('account_class', 'asset')
            ->where('status', true)
            ->whereHas('accounts', fn ($accounts) => $accounts->where('status', true))
            ->where(fn ($query) => $query
                ->where('name', 'like', '%cash%')
                ->orWhere('name', 'like', '%bank%'))
            ->get(['code', 'name'])
            ->map(fn (Group $group) => [
                'code' => $group->code,
                'name' => $group->name,
                'type' => str_contains(strtolower($group->name), 'cash') ? 'Cash' : $group->name,
            ]);
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'to_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_type' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 10)));
    }
}
