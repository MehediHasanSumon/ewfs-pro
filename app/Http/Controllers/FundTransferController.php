<?php

namespace App\Http\Controllers;

use App\Http\Requests\FundTransferRequest;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\FundTransfer;
use App\Services\FundTransferService;
use App\Services\PaymentAccountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class FundTransferController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly FundTransferService $transfers,
        private readonly PaymentAccountService $paymentAccounts
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf']),
            new Middleware('permission:view-account', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $options = $this->paymentAccounts->formOptions();

        $expenseAccounts = Account::query()
            ->active()
            ->whereHas('group', fn (Builder $q) => $q->where('account_class', 'expense'))
            ->orderBy('name')
            ->get(['id', 'name', 'ac_number']);

        $transfers = $this->filteredQuery($request)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('FundTransfers/Index', [
            'transfers' => $transfers,
            'paymentMethods' => $options['paymentMethods'],
            'paymentAccountGroups' => $options['paymentAccountGroups'],
            'paymentAccounts' => $options['paymentAccounts'],
            'expenseAccounts' => $expenseAccounts,
            'filters' => $request->only([
                'search',
                'start_date',
                'end_date',
                'from_account_id',
                'to_account_id',
                'status',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(FundTransferRequest $request)
    {
        $this->transfers->create($request->validated());

        return back()->with('success', 'Fund transfer posted successfully.');
    }

    public function update(FundTransferRequest $request, FundTransfer $fundTransfer)
    {
        $this->transfers->replace($fundTransfer, $request->validated());

        return back()->with('success', 'Fund transfer updated successfully.');
    }

    public function destroy(FundTransfer $fundTransfer)
    {
        $this->transfers->cancel($fundTransfer);

        return back()->with('success', 'Fund transfer cancelled successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (! empty($ids) && is_array($ids)) {
            $transfers = FundTransfer::query()->whereIn('id', $ids)->get();
            foreach ($transfers as $transfer) {
                $this->transfers->cancel($transfer, 'Bulk cancellation');
            }
        }

        return back()->with('success', 'Selected fund transfers cancelled successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $transfers = $this->filteredQuery($request)->get();
        $companySetting = CompanySetting::query()->first();

        $pdf = Pdf::loadView('pdf.fund-transfers', [
            'transfers' => $transfers,
            'companySetting' => $companySetting,
            'filters' => $request->only(['start_date', 'end_date', 'status']),
        ]);

        return $pdf->stream('fund-transfers.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = FundTransfer::query()
            ->with([
                'fromAccount.group:id,name,code',
                'toAccount.group:id,name,code',
                'feeAccount:id,name',
                'createdBy:id,name',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('transfer_no', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('fromAccount', fn (Builder $acc) => $acc->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('toAccount', fn (Builder $acc) => $acc->where('name', 'like', "%{$search}%"));
            });
        }

        $query
            ->when($request->start_date, fn (Builder $q, $date) => $q->whereDate('transfer_date', '>=', $date))
            ->when($request->end_date, fn (Builder $q, $date) => $q->whereDate('transfer_date', '<=', $date))
            ->when($request->from_account_id, fn (Builder $q, $id) => $q->where('from_account_id', $id))
            ->when($request->to_account_id, fn (Builder $q, $id) => $q->where('to_account_id', $id))
            ->when($request->status, fn (Builder $q, $status) => $q->where('status', $status));

        $sortBy = in_array($request->sort_by, ['id', 'transfer_date', 'transfer_no', 'amount', 'created_at'], true)
            ? $request->sort_by
            : 'transfer_date';

        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->orderByDesc('id');
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->integer('per_page', 15)));
    }
}
