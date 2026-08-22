<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Group;
use App\Models\CompanySetting;
use App\Services\DocumentNumberService;
use App\Services\LedgerQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class AccountController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account|can-account-download', only: ['index', 'show', 'downloadPdf', 'downloadStatementPdf']),
            new Middleware('permission:create-account', only: ['store']),
            new Middleware('permission:update-account', only: ['update']),
            new Middleware('permission:delete-account', only: ['destroy']),
        ];
    }
    public function index(Request $request)
    {
        $query = Account::query()
            ->select('id', 'name', 'ac_number', 'group_id', 'is_system', 'status', 'created_at')
            ->with('group:id,code,name');

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('ac_number', 'like', '%' . $request->search . '%')
                    ->orWhereHas('group', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->group && $request->group !== 'all') {
            $query->whereHas('group', fn ($group) => $group
                ->where('code', $request->group));
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        // Apply sorting
        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'name', 'ac_number', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $accounts = $query->paginate($perPage)->withQueryString()->through(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'ac_number' => $account->ac_number,
                'group_id' => $account->group_id,
                'group_code' => $account->group?->code,
                'due_amount' => 0,
                'paid_amount' => 0,
                'status' => $account->status,
                'group' => $account->group,
                'created_at' => $account->created_at->format('Y-m-d'),
            ];
        });

        $groups = Group::where('status', true)->get(['id', 'code', 'name']);

        return Inertia::render('Accounts/Accounts', [
            'accounts' => $accounts,
            'groups' => $groups,
            'filters' => $request->only(['search', 'group', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'group_id' => 'required|exists:groups,id',
            'status' => 'boolean'
        ]);

        DB::transaction(function () use ($request) {
            Account::query()->create([
                'name' => $request->name,
                'ac_number' => $this->numbers->next('account', 'AC'),
                'group_id' => $request->integer('group_id'),
                'currency' => 'BDT',
                'is_control_account' => false,
                'allow_manual_posting' => true,
                'is_system' => false,
                'status' => $request->boolean('status', true),
            ]);
        });

        return redirect()->back()->with('success', 'Account created successfully.');
    }

    public function update(Request $request, Account $account)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'ac_number' => 'required|string|max:150|unique:accounts,ac_number,' . $account->id,
            'group_id' => 'required|exists:groups,id',
            'status' => 'boolean'
        ]);

        if ($account->is_system && $account->group_id !== $request->integer('group_id')) {
            throw ValidationException::withMessages([
                'group_id' => 'System accounts cannot be moved to another group.',
            ]);
        }

        $account->update([
            'name' => $request->name,
            'ac_number' => $request->ac_number,
            'group_id' => $request->integer('group_id'),
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->back()->with('success', 'Account updated successfully.');
    }

    public function destroy(Account $account)
    {
        if ($account->is_system) {
            throw ValidationException::withMessages([
                'account' => 'System accounts cannot be deleted.',
            ]);
        }

        if (
            $account->journalLines()->exists()
            || $account->customer()->exists()
            || $account->supplier()->exists()
            || $account->employee()->exists()
            || $account->dailyBalances()->exists()
        ) {
            throw ValidationException::withMessages([
                'account' => 'This account has ledger or party records and cannot be deleted.',
            ]);
        }

        $account->delete();

        return redirect()->back()->with('success', 'Account deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Account::with('group');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('ac_number', 'like', '%' . $request->search . '%')
                    ->orWhereHas('group', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->group && $request->group !== 'all') {
            $query->whereHas('group', fn ($group) => $group
                ->where('code', $request->group));
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'name', 'ac_number', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $accounts = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.accounts', compact('accounts', 'companySetting'));
        return $pdf->stream();
    }

    public function show(Request $request, Account $account, LedgerQueryService $ledger)
    {
        $account->loadMissing([
            'group',
            'customer:id,account_id,name,code,mobile',
            'supplier:id,account_id,name,mobile',
            'employee:id,account_id,employee_name,employee_code,designation_id',
            'employee.designation:id,name',
        ]);

        $startDate = $request->get('start_date', date('Y-m-d'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));

        $result = $ledger->paginatedAccountLedger($account, $startDate, $endDate, $perPage);

        // Overall stats (all-time)
        $allTimeTotals = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $account->id)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw('
                COUNT(jl.id) as total_count,
                COALESCE(SUM(jl.debit_amount), 0) as total_debit,
                COALESCE(SUM(jl.credit_amount), 0) as total_credit
            ')
            ->first();

        $isCreditNormal = $account->group?->normal_balance === 'credit';
        $allTimeBalance = $isCreditNormal
            ? (float) $allTimeTotals->total_credit - (float) $allTimeTotals->total_debit
            : (float) $allTimeTotals->total_debit - (float) $allTimeTotals->total_credit;

        $groups = Group::where('status', true)->get(['id', 'code', 'name']);

        return Inertia::render('Accounts/AccountDetails', [
            'account' => $account,
            'groups' => $groups,
            'transactions' => $result['transactions'],
            'openingBalance' => (float) $result['opening_balance'],
            'periodDebit' => (float) $result['total_debit'],
            'periodCredit' => (float) $result['total_credit'],
            'closingBalance' => (float) $result['closing_balance'],
            'allTimeBalance' => (float) $allTimeBalance,
            'allTimeDebit' => (float) $allTimeTotals->total_debit,
            'allTimeCredit' => (float) $allTimeTotals->total_credit,
            'transactionCount' => (int) $allTimeTotals->total_count,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $request->get('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function downloadStatementPdf(Request $request, Account $account, LedgerQueryService $ledger)
    {
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        $account->loadMissing('group');
        $result = $ledger->accountLedger($account, $startDate, $endDate);
        $transactions = $result['transactions'];
        $openingBalance = $result['opening_balance'];
        $closingBalance = $result['closing_balance'];
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.general-ledger', compact(
            'account',
            'transactions',
            'companySetting',
            'startDate',
            'endDate'
        ));

        return $pdf->stream("account-{$account->ac_number}-statement.pdf");
    }
}
