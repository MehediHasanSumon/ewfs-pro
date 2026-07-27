<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Account;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Stock;
use App\Models\CompanySetting;
use App\Helpers\TransactionHelper;
use App\Helpers\InvoiceHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class PurchaseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-purchase', only: ['index']),
            new Middleware('permission:view-purchase|can-purchase-download', only: ['downloadPdf']),
            new Middleware('permission:create-purchase', only: ['store']),
            new Middleware('permission:update-purchase', only: ['edit', 'update']),
            new Middleware('permission:delete-purchase', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'product', 'fromAccount', 'transaction']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                    ->orWhereHas('supplier', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->supplier && $request->supplier !== 'all') {
            $query->whereHas('supplier', function ($q) use ($request) {
                $q->where('name', $request->supplier);
            });
        }

        if ($request->payment_status && $request->payment_status !== 'all') {
            if ($request->payment_status === 'paid') {
                $query->where('due_amount', 0);
            } elseif ($request->payment_status === 'partial') {
                $query->where('paid_amount', '>', 0)->where('due_amount', '>', 0);
            } elseif ($request->payment_status === 'due') {
                $query->where('paid_amount', 0);
            }
        }

        if ($request->start_date) {
            $query->where('purchase_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('purchase_date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $purchases = $query->paginate($request->per_page ?? 10);

        $purchases->getCollection()->transform(function ($purchase) {
            $purchase->payment_type = $purchase->transaction->payment_type ?? 'N/A';
            return $purchase;
        });

        $accounts = Account::with('group')
            ->select('id', 'name', 'ac_number', 'group_id', 'group_code')
            ->get();

        $groupedAccounts = $accounts->groupBy(function ($account) {
            return $account->group ? $account->group->name : 'Other';
        });

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::select('id', 'name')->get(),
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'products' => Product::with(['stock', 'activeRate'])->select('id', 'product_name')->get()->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'purchase_price' => $product->activeRate ? (float) $product->activeRate->purchase_price : 0,
                    'stock' => $product->stock ? [
                        'current_stock' => $product->stock->current_stock,
                        'available_stock' => $product->stock->available_stock
                    ] : null
                ];
            }),
            'filters' => $request->only(['search', 'supplier', 'payment_status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_date' => 'required|date',
            'memo_no' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.supplier_id' => 'required|exists:suppliers,id',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.payment_type' => 'required|in:Cash,Bank,Mobile Bank',
            'products.*.from_account_id' => 'required|exists:accounts,id',
            'products.*.paid_amount' => 'required|numeric|min:0',
            'products.*.due_amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->products as $productData) {
                if (!isset($productData['product_id']) || !$productData['product_id']) {
                    continue;
                }

                $fromAccount = Account::find($productData['from_account_id']);
                $supplier = Supplier::with('account')->find($productData['supplier_id']);
                $product = Product::find($productData['product_id']);

                $transactionId = TransactionHelper::generateTransactionId();

                $transaction = Transaction::create([
                    'transaction_id' => $transactionId,
                    'ac_number' => $fromAccount->ac_number,
                    'transaction_type' => 'Dr',
                    'amount' => $productData['paid_amount'],
                    'description' => 'Purchase ' . $product->product_name . ' from ' . $supplier->name . ' - Invoice: ' . InvoiceHelper::generateInvoiceId(),
                    'payment_type' => strtolower($productData['payment_type']),
                    'bank_name' => $productData['bank_name'] ?? null,
                    'branch_name' => $productData['branch_name'] ?? null,
                    'account_number' => $productData['account_no'] ?? null,
                    'cheque_type' => $productData['bank_type'] ?? null,
                    'cheque_no' => $productData['cheque_no'] ?? null,
                    'cheque_date' => $productData['cheque_date'] ?? null,
                    'mobile_bank_name' => $productData['mobile_bank'] ?? null,
                    'mobile_number' => $productData['mobile_number'] ?? null,
                    'transaction_date' => $request->purchase_date,
                    'transaction_time' => now()->format('H:i:s'),
                ]);

                if ($productData['paid_amount'] > 0) {
                    Transaction::create([
                        'transaction_id' => $transactionId,
                        'ac_number' => $supplier->account->ac_number,
                        'transaction_type' => 'Cr',
                        'amount' => $productData['paid_amount'],
                        'description' => 'Payment received for ' . $product->product_name . ' - Invoice: ' . InvoiceHelper::generateInvoiceId(),
                        'payment_type' => strtolower($productData['payment_type']),
                        'transaction_date' => $request->purchase_date,
                        'transaction_time' => now()->format('H:i:s'),
                    ]);
                }

                if ($productData['due_amount'] > 0) {
                    $payableAccount = Account::where('name', 'Accounts Payable')->first();
                    if ($payableAccount) {
                        Transaction::create([
                            'transaction_id' => $transactionId,
                            'ac_number' => $payableAccount->ac_number,
                            'transaction_type' => 'Cr',
                            'amount' => $productData['due_amount'],
                            'description' => 'Due amount for ' . $product->product_name . ' - Invoice: ' . InvoiceHelper::generateInvoiceId(),
                            'payment_type' => 'credit',
                            'transaction_date' => $request->purchase_date,
                            'transaction_time' => now()->format('H:i:s'),
                        ]);

                        $payableAccount->increment('total_amount', $productData['due_amount']);
                    }
                }

                $fromAccount->decrement('total_amount', $productData['paid_amount']);
                if ($productData['paid_amount'] > 0) {
                    $supplier->account->increment('total_amount', $productData['paid_amount']);
                }

                $netTotal = $productData['amount'] - ($productData['discount'] ?? 0);
                $paidAmount = $productData['paid_amount'];
                $dueAmount = $productData['due_amount'];

                // Calculate status
                $status = 'due';
                if ($dueAmount == 0) {
                    $status = 'paid';
                } elseif ($paidAmount > 0) {
                    $status = 'partial';
                }

                Purchase::create([
                    'purchase_date' => $request->purchase_date,
                    'supplier_id' => $productData['supplier_id'],
                    'product_id' => $productData['product_id'],
                    'transaction_id' => $transaction->id,
                    'invoice_no' => InvoiceHelper::generateInvoiceId(),
                    'memo_no' => $request->memo_no,
                    'from_account_id' => $productData['from_account_id'],
                    'quantity' => $productData['quantity'],
                    'unit_price' => $productData['unit_price'],
                    'discount' => $productData['discount'] ?? 0,
                    'net_total_amount' => $netTotal,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'status' => $status,
                    'remarks' => $request->remarks,
                ]);

                $stock = Stock::firstOrCreate(
                    ['product_id' => $productData['product_id']],
                    [
                        'opening_stock' => 0,
                        'current_stock' => 0,
                        'reserved_stock' => 0,
                        'available_stock' => 0,
                        'minimum_stock' => 0,
                    ]
                );

                $quantity = $productData['quantity'] ?? 0;
                $stock->increment('current_stock', $quantity);
                $stock->increment('available_stock', $quantity);
            }
        });

        return redirect()->back()->with('success', 'Purchase created successfully.');
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load(['supplier', 'product', 'fromAccount', 'transaction']);
        return response()->json(['purchase' => $purchase]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $request->validate([
            'purchase_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'product_id' => 'required|exists:products,id',
            'memo_no' => 'required|string|max:255',
            'from_account_id' => 'required|exists:accounts,id',
            'payment_type' => 'required|in:Cash,Bank,Mobile Bank',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $purchase) {
            $purchase->fromAccount->increment('total_amount', $purchase->paid_amount);
            if ($purchase->paid_amount > 0) {
                $purchase->supplier->account->decrement('total_amount', $purchase->paid_amount);
            }

            if ($purchase->due_amount > 0) {
                $payableAccount = Account::where('name', 'Accounts Payable')->first();
                if ($payableAccount) {
                    $payableAccount->decrement('total_amount', $purchase->due_amount);
                }
            }

            $oldStock = Stock::where('product_id', $purchase->product_id)->first();
            if ($oldStock) {
                $oldStock->decrement('current_stock', $purchase->quantity);
                $oldStock->decrement('available_stock', $purchase->quantity);
            }

            $fromAccount = Account::find($request->from_account_id);
            $supplier = Supplier::with('account')->find($request->supplier_id);
            $product = Product::find($request->product_id);

            $netTotal = ($request->unit_price * $request->quantity) - ($request->discount ?? 0);
            $paidAmount = $request->paid_amount;
            $dueAmount = $request->due_amount;

            // Calculate status
            $status = 'due';
            if ($dueAmount == 0) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            }

            $purchase->update([
                'purchase_date' => $request->purchase_date,
                'supplier_id' => $request->supplier_id,
                'product_id' => $request->product_id,
                'memo_no' => $request->memo_no,
                'from_account_id' => $request->from_account_id,
                'unit_price' => $request->unit_price,
                'quantity' => $request->quantity,
                'discount' => $request->discount ?? 0,
                'net_total_amount' => $netTotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'status' => $status,
                'remarks' => $request->remarks,
            ]);

            $transactionId = $purchase->transaction->transaction_id;
            $newTransactionId = TransactionHelper::generateTransactionId();

            Transaction::create([
                'transaction_id' => $newTransactionId,
                'ac_number' => $fromAccount->ac_number,
                'transaction_type' => 'Dr',
                'amount' => $request->paid_amount,
                'description' => 'Purchase ' . $product->product_name . ' from ' . $supplier->name . ' - Invoice: ' . $request->supplier_invoice_no,
                'payment_type' => strtolower($request->payment_type),
                'bank_name' => $request->bank_name ?? null,
                'branch_name' => $request->branch_name ?? null,
                'account_number' => $request->account_no ?? null,
                'cheque_type' => $request->bank_type ?? null,
                'cheque_no' => $request->cheque_no ?? null,
                'cheque_date' => $request->cheque_date ?? null,
                'mobile_bank_name' => $request->mobile_bank ?? null,
                'mobile_number' => $request->mobile_number ?? null,
                'transaction_date' => $request->purchase_date,
                'transaction_time' => now()->format('H:i:s'),
            ]);

            if ($request->paid_amount > 0) {
                Transaction::create([
                    'transaction_id' => $newTransactionId,
                    'ac_number' => $supplier->account->ac_number,
                    'transaction_type' => 'Cr',
                    'amount' => $request->paid_amount,
                    'description' => 'Payment received for ' . $product->product_name . ' - Invoice: ' . $request->supplier_invoice_no,
                    'payment_type' => strtolower($request->payment_type),
                    'transaction_date' => $request->purchase_date,
                    'transaction_time' => now()->format('H:i:s'),
                ]);
            }

            if ($request->due_amount > 0) {
                $payableAccount = Account::where('name', 'Accounts Payable')->first();
                if ($payableAccount) {
                    Transaction::create([
                        'transaction_id' => $newTransactionId,
                        'ac_number' => $payableAccount->ac_number,
                        'transaction_type' => 'Cr',
                        'amount' => $request->due_amount,
                        'description' => 'Due amount for ' . $product->product_name . ' - Invoice: ' . $request->supplier_invoice_no,
                        'payment_type' => 'credit',
                        'transaction_date' => $request->purchase_date,
                        'transaction_time' => now()->format('H:i:s'),
                    ]);
                }
            }

            $newTransaction = Transaction::where('transaction_id', $newTransactionId)->where('transaction_type', 'Dr')->first();
            $purchase->update(['transaction_id' => $newTransaction->id]);

            Transaction::where('transaction_id', $transactionId)->delete();

            $fromAccount->decrement('total_amount', $request->paid_amount);
            if ($request->paid_amount > 0) {
                $supplier->account->increment('total_amount', $request->paid_amount);
            }

            if ($request->due_amount > 0) {
                $payableAccount = Account::where('name', 'Accounts Payable')->first();
                if ($payableAccount) {
                    $payableAccount->increment('total_amount', $request->due_amount);
                }
            }

            $newStock = Stock::firstOrCreate(
                ['product_id' => $request->product_id],
                [
                    'opening_stock' => 0,
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'available_stock' => 0,
                    'minimum_stock' => 0,
                ]
            );
            $newStock->increment('current_stock', $request->quantity);
            $newStock->increment('available_stock', $request->quantity);
        });

        return redirect()->back()->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $purchase->fromAccount->increment('total_amount', $purchase->paid_amount);
            if ($purchase->paid_amount > 0) {
                $purchase->supplier->account->decrement('total_amount', $purchase->paid_amount);
            }

            if ($purchase->due_amount > 0) {
                $payableAccount = Account::where('name', 'Accounts Payable')->first();
                if ($payableAccount) {
                    $payableAccount->decrement('total_amount', $purchase->due_amount);
                }
            }

            $stock = Stock::where('product_id', $purchase->product_id)->first();
            if ($stock) {
                $stock->decrement('current_stock', $purchase->quantity);
                $stock->decrement('available_stock', $purchase->quantity);
            }

            $transactionId = $purchase->transaction->transaction_id;

            $purchase->delete();

            if ($transactionId) {
                Transaction::where('transaction_id', $transactionId)->delete();
            }
        });

        return redirect()->back()->with('success', 'Purchase deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:purchases,id'
        ]);

        DB::transaction(function () use ($request) {
            $purchases = Purchase::with(['fromAccount', 'supplier.account'])->whereIn('id', $request->ids)->get();

            foreach ($purchases as $purchase) {
                $purchase->fromAccount->increment('total_amount', $purchase->paid_amount);
                if ($purchase->paid_amount > 0) {
                    $purchase->supplier->account->decrement('total_amount', $purchase->paid_amount);
                }

                if ($purchase->due_amount > 0) {
                    $payableAccount = Account::where('name', 'Accounts Payable')->first();
                    if ($payableAccount) {
                        $payableAccount->decrement('total_amount', $purchase->due_amount);
                    }
                }

                $stock = Stock::where('product_id', $purchase->product_id)->first();
                if ($stock) {
                    $stock->decrement('current_stock', $purchase->quantity);
                    $stock->decrement('available_stock', $purchase->quantity);
                }

                $transactionId = $purchase->transaction->transaction_id;

                $purchase->delete();

                if ($transactionId) {
                    Transaction::where('transaction_id', $transactionId)->delete();
                }
            }
        });

        return redirect()->back()->with('success', 'Purchases deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Purchase::with(['supplier', 'product', 'fromAccount', 'transaction']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                    ->orWhereHas('supplier', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->supplier && $request->supplier !== 'all') {
            $query->whereHas('supplier', function ($q) use ($request) {
                $q->where('name', $request->supplier);
            });
        }

        if ($request->payment_status && $request->payment_status !== 'all') {
            if ($request->payment_status === 'paid') {
                $query->where('due_amount', 0);
            } elseif ($request->payment_status === 'partial') {
                $query->where('paid_amount', '>', 0)->where('due_amount', '>', 0);
            } elseif ($request->payment_status === 'due') {
                $query->where('paid_amount', 0);
            }
        }

        if ($request->start_date) {
            $query->where('purchase_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('purchase_date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $purchases = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.purchases', compact('purchases', 'companySetting'));
        return $pdf->stream();
    }
}
