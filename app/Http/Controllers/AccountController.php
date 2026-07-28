<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Group;
use App\Models\CompanySetting;
use App\Services\DocumentNumberService;
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
            new Middleware('permission:view-account|can-account-download', only: ['index', 'downloadPdf']),
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
}
