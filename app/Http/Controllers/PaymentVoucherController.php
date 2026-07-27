<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\Account;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\CompanySetting;
use App\Models\VoucherCategory;
use App\Models\PaymentSubType;
use App\Models\IsShiftClose;
use App\Helpers\TransactionHelper;
use App\Helpers\VoucherHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class PaymentVoucherController extends Controller implements HasMiddleware
{
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
        $query = Voucher::with(['fromAccount', 'toAccount', 'shift', 'voucherCategory', 'paymentSubType', 'transaction'])
            ->where('voucher_type', 'Payment');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('fromAccount', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                })
                    ->orWhereHas('toAccount', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }
        if ($request->payment_method && $request->payment_method !== 'all') {
            $query->whereHas('transaction', function($q) use ($request) {
                $q->where('payment_type', strtolower($request->payment_method));
            });
        }
        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }
        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $vouchers = $query->paginate($request->per_page ?? 10);

        $vouchers->getCollection()->transform(function ($voucher) {
            $voucher->payment_type = $voucher->payment_method;
            $voucher->from_account = $voucher->fromAccount;
            $voucher->to_account = $voucher->toAccount;
            $voucher->shift = $voucher->shift;
            return $voucher;
        });

        $shifts = Shift::where('status', true)->select('id', 'name')->get();
        $closedShifts = IsShiftClose::select('close_date', 'shift_id')->get()->map(function($item) {
            return [
                'close_date' => $item->close_date->format('Y-m-d'),
                'shift_id' => $item->shift_id
            ];
        });
        $accounts = Account::select('id', 'name', 'ac_number')->get();
        $groupedAccounts = Account::with('group')
            ->select('id', 'name', 'ac_number', 'group_code')
            ->get()
            ->groupBy(function ($account) {
                return $account->group ? $account->group->name : 'Other';
            });
        $voucherCategories = VoucherCategory::where('status', true)->get();
        $paymentSubTypes = PaymentSubType::with('voucherCategory')->where('status', true)->get();

        return Inertia::render('Vouchers/PaymentVoucher', [
            'vouchers' => $vouchers,
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'shifts' => $shifts,
            'closedShifts' => $closedShifts,
            'voucherCategories' => $voucherCategories,
            'paymentSubTypes' => $paymentSubTypes,
            'filters' => $request->only(['search', 'payment_method', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'shift_id' => 'nullable|exists:shifts,id',
            'vouchers' => 'required|array|min:1',
            'vouchers.*.voucher_category_id' => 'required|exists:voucher_categories,id',
            'vouchers.*.payment_sub_type_id' => 'required|exists:payment_sub_types,id',
            'vouchers.*.from_account_id' => 'required|exists:accounts,id',
            'vouchers.*.to_account_id' => 'required|exists:accounts,id',
            'vouchers.*.amount' => 'required|numeric|min:0',
            'vouchers.*.payment_method' => 'required|in:Cash,Bank,Mobile Bank',
            'vouchers.*.description' => 'nullable|string',
            'vouchers.*.remarks' => 'nullable|string',
            'vouchers.*.bank_name' => 'nullable|string',
            'vouchers.*.branch_name' => 'nullable|string',
            'vouchers.*.account_no' => 'nullable|string',
            'vouchers.*.bank_type' => 'nullable|string',
            'vouchers.*.cheque_no' => 'nullable|string',
            'vouchers.*.cheque_date' => 'nullable|date',
            'vouchers.*.mobile_bank' => 'nullable|string',
            'vouchers.*.mobile_number' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->vouchers as $voucherData) {
                $fromAccount = Account::find($voucherData['from_account_id']);
                $toAccount = Account::find($voucherData['to_account_id']);
                $fromAccount->decrement('total_amount', $voucherData['amount']);
                $toAccount->increment('total_amount', $voucherData['amount']);

                $transactionId = TransactionHelper::generateTransactionId();

                $drTransaction = Transaction::create([
                    'transaction_id' => $transactionId,
                    'ac_number' => $toAccount->ac_number,
                    'transaction_type' => 'Dr',
                    'amount' => $voucherData['amount'],
                    'description' => $voucherData['description'] ?? 'Payment to ' . $toAccount->name,
                    'payment_type' => strtolower($voucherData['payment_method']),
                    'bank_name' => $voucherData['bank_name'] ?? null,
                    'branch_name' => $voucherData['branch_name'] ?? null,
                    'account_number' => $voucherData['account_no'] ?? null,
                    'cheque_type' => $voucherData['bank_type'] ?? null,
                    'cheque_no' => $voucherData['cheque_no'] ?? null,
                    'cheque_date' => $voucherData['cheque_date'] ?? null,
                    'mobile_bank_name' => $voucherData['mobile_bank'] ?? null,
                    'mobile_number' => $voucherData['mobile_number'] ?? null,
                    'transaction_date' => $request->date,
                    'transaction_time' => now()->format('H:i:s'),
                ]);

                Transaction::create([
                    'transaction_id' => $transactionId,
                    'ac_number' => $fromAccount->ac_number,
                    'transaction_type' => 'Cr',
                    'amount' => $voucherData['amount'],
                    'description' => $voucherData['description'] ?? 'Payment from ' . $fromAccount->name,
                    'payment_type' => strtolower($voucherData['payment_method']),
                    'bank_name' => $voucherData['bank_name'] ?? null,
                    'branch_name' => $voucherData['branch_name'] ?? null,
                    'account_number' => $voucherData['account_no'] ?? null,
                    'cheque_type' => $voucherData['bank_type'] ?? null,
                    'cheque_no' => $voucherData['cheque_no'] ?? null,
                    'cheque_date' => $voucherData['cheque_date'] ?? null,
                    'mobile_bank_name' => $voucherData['mobile_bank'] ?? null,
                    'mobile_number' => $voucherData['mobile_number'] ?? null,
                    'transaction_date' => $request->date,
                    'transaction_time' => now()->format('H:i:s'),
                ]);

                Voucher::create([
                    'voucher_no' => VoucherHelper::generateVoucherNo(),
                    'voucher_type' => 'Payment',
                    'voucher_category_id' => $voucherData['voucher_category_id'],
                    'payment_sub_type_id' => $voucherData['payment_sub_type_id'],
                    'date' => $request->date,
                    'shift_id' => $request->shift_id,
                    'from_account_id' => $voucherData['from_account_id'],
                    'to_account_id' => $voucherData['to_account_id'],
                    'transaction_id' => $drTransaction->id,
                    'description' => $voucherData['description'] ?? null,
                    'remarks' => $voucherData['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Payment voucher created successfully.');
    }

    public function update(Request $request, Voucher $voucher)
    {
        $request->validate([
            'date' => 'required|date',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'payment_sub_type_id' => 'required|exists:payment_sub_types,id',
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:Cash,Bank,Mobile Bank',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $voucher) {
            $oldFromAccount = Account::find($voucher->from_account_id);
            $oldToAccount = Account::find($voucher->to_account_id);
            $oldAmount = $voucher->transaction->amount;
            $oldFromAccount->increment('total_amount', $oldAmount);
            $oldToAccount->decrement('total_amount', $oldAmount);

            $newFromAccount = Account::find($request->from_account_id);
            $newToAccount = Account::find($request->to_account_id);
            $newFromAccount->decrement('total_amount', $request->amount);
            $newToAccount->increment('total_amount', $request->amount);

            $transactionId = TransactionHelper::generateTransactionId();
            
            $drTransaction = Transaction::create([
                'transaction_id' => $transactionId,
                'ac_number' => $newToAccount->ac_number,
                'transaction_type' => 'Dr',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Payment to ' . $newToAccount->name,
                'payment_type' => strtolower($request->payment_method),
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'account_number' => $request->account_no,
                'cheque_type' => $request->bank_type,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'mobile_bank_name' => $request->mobile_bank,
                'mobile_number' => $request->mobile_number,
                'transaction_date' => $request->date,
                'transaction_time' => now()->format('H:i:s'),
            ]);

            Transaction::create([
                'transaction_id' => $transactionId,
                'ac_number' => $newFromAccount->ac_number,
                'transaction_type' => 'Cr',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Payment from ' . $newFromAccount->name,
                'payment_type' => strtolower($request->payment_method),
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'account_number' => $request->account_no,
                'cheque_type' => $request->bank_type,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'mobile_bank_name' => $request->mobile_bank,
                'mobile_number' => $request->mobile_number,
                'transaction_date' => $request->date,
                'transaction_time' => now()->format('H:i:s'),
            ]);

            $voucher->update([
                'voucher_category_id' => $request->voucher_category_id,
                'payment_sub_type_id' => $request->payment_sub_type_id,
                'date' => $request->date,
                'shift_id' => $request->shift_id,
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'transaction_id' => $drTransaction->id,
                'description' => $request->description,
                'remarks' => $request->remarks,
            ]);

            Transaction::where('transaction_id', $voucher->transaction->transaction_id)->delete();
        });

        return redirect()->back()->with('success', 'Payment voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        DB::transaction(function () use ($voucher) {
            $fromAccount = Account::find($voucher->from_account_id);
            $toAccount = Account::find($voucher->to_account_id);
            $amount = $voucher->transaction->amount;
            $fromAccount->increment('total_amount', $amount);
            $toAccount->decrement('total_amount', $amount);
            $voucher->delete();
            Transaction::where('transaction_id', $voucher->transaction->transaction_id)->delete();
        });

        return redirect()->back()->with('success', 'Payment voucher deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:vouchers,id'
        ]);

        DB::transaction(function () use ($request) {
            $vouchers = Voucher::whereIn('id', $request->ids)->get();
            foreach ($vouchers as $voucher) {
                $fromAccount = Account::find($voucher->from_account_id);
                $toAccount = Account::find($voucher->to_account_id);
                $amount = $voucher->transaction->amount;
                $fromAccount->increment('total_amount', $amount);
                $toAccount->decrement('total_amount', $amount);
                $voucher->delete();
                Transaction::where('transaction_id', $voucher->transaction->transaction_id)->delete();
            }
        });

        return redirect()->back()->with('success', 'Payment vouchers deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Voucher::with(['fromAccount', 'toAccount', 'shift'])
            ->where('voucher_type', 'Payment');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('fromAccount', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                })
                    ->orWhereHas('toAccount', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->payment_method && $request->payment_method !== 'all') {
            $query->whereHas('transaction', function($q) use ($request) {
                $q->where('payment_type', strtolower($request->payment_method));
            });
        }

        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $vouchers = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.payment-vouchers', compact('vouchers', 'companySetting'));
        return $pdf->stream();
    }
}
