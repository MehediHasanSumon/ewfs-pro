<?php

namespace App\Http\Controllers;

use App\Models\OfficePayment;
use App\Models\Account;
use App\Models\Shift;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\IsShiftClose;
use App\Models\CompanySetting;
use App\Helpers\TransactionHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class OfficePaymentController extends Controller implements HasMiddleware
{
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
        $query = OfficePayment::with(['shift', 'to_account', 'transaction']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('to_account', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $officePayments = $query->paginate($request->per_page ?? 10);

        $officePayments->getCollection()->transform(function ($payment) {
            $payment->payment_type = $payment->transaction?->payment_type ?? 'Cash';
            $payment->amount = $payment->transaction?->amount ?? 0;
            return $payment;
        });

        $accounts = Account::select('id', 'name', 'ac_number')->get();
        $groupedAccounts = Account::with('group')
            ->select('id', 'name', 'ac_number', 'group_code')
            ->get()
            ->groupBy(function ($account) {
                return $account->group ? $account->group->name : 'Other';
            });
        $shifts = Shift::select('id', 'name')->where('status', true)->get();

        $paymentTypes = Group::whereIn('code', ['100020002', '100020003', '100020004'])
            ->whereHas('accounts')
            ->select('code', 'name')
            ->get()
            ->map(function ($group) {
                return ['code' => $group->code, 'name' => $group->name, 'type' => $group->name === 'Cash in hand' ? 'Cash' : $group->name];
            });
        $filters = $request->only(['search', 'start_date', 'end_date', 'type', 'shift_id', 'sort_by', 'sort_order', 'per_page']);
        $types = [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank', 'label' => 'Bank']
        ];

        $closedShifts = IsShiftClose::select('close_date', 'shift_id')->get()->map(function($item) {
            return [
                'close_date' => $item->close_date->format('Y-m-d'),
                'shift_id' => $item->shift_id
            ];
        });

        return Inertia::render('OfficePayments/Index', [
            'officePayments' => $officePayments,
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'shifts' => $shifts,
            'closedShifts' => $closedShifts,
            'paymentTypes' => $paymentTypes,
            'types' => $types,
            'filters' => $filters
        ]);
    }

    public function store(Request $request)
    {
        $validPaymentTypes = Group::whereIn('code', ['100020002', '100020003', '100020004'])
            ->whereHas('accounts')
            ->get()
            ->map(function ($group) {
                return $group->name === 'Cash in hand' ? 'Cash' : $group->name;
            })
            ->toArray();

        $request->validate([
            'date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:' . implode(',', $validPaymentTypes),
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $toAccount = Account::find($request->to_account_id);
            $transactionId = TransactionHelper::generateTransactionId();

            $transaction = Transaction::create([
                'transaction_id' => $transactionId,
                'ac_number' => $toAccount->ac_number,
                'transaction_type' => 'Cr',
                'amount' => $request->amount,
                'description' => 'Office payment',
                'payment_type' => strtolower($request->payment_type),
                'transaction_date' => $request->date,
                'transaction_time' => now()->format('H:i:s'),
            ]);

            $toAccount->increment('total_amount', $request->amount);

            OfficePayment::create([
                'date' => $request->date,
                'shift_id' => $request->shift_id,
                'transaction_id' => $transaction->id,
                'to_account_id' => $request->to_account_id,
                'type' => strtolower($request->payment_type),
                'remarks' => $request->remarks,
            ]);
        });

        return redirect()->back()->with('success', 'Office payment created successfully.');
    }

    public function update(Request $request, OfficePayment $officePayment)
    {
        $validPaymentTypes = Group::whereIn('code', ['100020002', '100020003', '100020004'])
            ->whereHas('accounts')
            ->get()
            ->map(function ($group) {
                return $group->name === 'Cash in hand' ? 'Cash' : $group->name;
            })
            ->toArray();

        $request->validate([
            'date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:' . implode(',', $validPaymentTypes),
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $officePayment) {
            $originalToAccount = $officePayment->to_account;
            $originalAmount = $officePayment->transaction->amount;

            $originalToAccount->decrement('total_amount', $originalAmount);

            $toAccount = Account::find($request->to_account_id);
            $newTransactionId = TransactionHelper::generateTransactionId();

            $officePayment->transaction->update([
                'transaction_id' => $newTransactionId,
                'ac_number' => $toAccount->ac_number,
                'amount' => $request->amount,
                'description' => 'Office payment',
                'payment_type' => strtolower($request->payment_type),
                'transaction_date' => $request->date,
                'transaction_time' => now()->format('H:i:s'),
            ]);

            $toAccount->increment('total_amount', $request->amount);

            $officePayment->update([
                'date' => $request->date,
                'shift_id' => $request->shift_id,
                'to_account_id' => $request->to_account_id,
                'type' => strtolower($request->payment_type),
                'remarks' => $request->remarks,
            ]);
        });

        return redirect()->back()->with('success', 'Office payment updated successfully.');
    }

    public function destroy(OfficePayment $officePayment)
    {
        DB::transaction(function () use ($officePayment) {
            $amount = $officePayment->transaction->amount;
            $officePayment->to_account->decrement('total_amount', $amount);

            $officePayment->delete();
            $officePayment->transaction?->delete();
        });

        return redirect()->back()->with('success', 'Office payment deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:office_payments,id'
        ]);

        DB::transaction(function () use ($request) {
            $officePayments = OfficePayment::with(['to_account', 'transaction'])->whereIn('id', $request->ids)->get();

            foreach ($officePayments as $payment) {
                $amount = $payment->transaction->amount;
                $payment->to_account->decrement('total_amount', $amount);
                $payment->delete();
                $payment->transaction?->delete();
            }
        });

        return redirect()->back()->with('success', 'Office payments deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = OfficePayment::with(['shift', 'to_account', 'transaction']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('to_account', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $officePayments = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.office-payments', compact('officePayments', 'companySetting'));
        return $pdf->stream();
    }
}
