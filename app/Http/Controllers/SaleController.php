<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\IsShiftClose;
use App\Models\CompanySetting;
use App\Helpers\TransactionHelper;
use App\Helpers\InvoiceHelper;
use App\Helpers\BatchHelper;
use App\Helpers\NumberToWordsHelper;
use App\Models\SaleBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class SaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-sale', only: ['index', 'downloadBatchPdf']),
            new Middleware('permission:view-sale|can-sale-download', only: ['downloadPdf']),
            new Middleware('permission:create-sale', only: ['store']),
            new Middleware('permission:update-sale', only: ['edit', 'update']),
            new Middleware('permission:delete-sale', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = Sale::with(['product', 'shift', 'transaction'])
            ->leftJoin('sale_batches', 'sales.id', '=', 'sale_batches.sale_id')
            ->select('sales.*', 'sale_batches.batch_code');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                    ->orWhere('customer', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer', $request->customer);
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
            $query->where('sale_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('sale_date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $sales = $query->paginate($request->per_page ?? 10);

        $uniqueCustomers = Sale::select('customer')->distinct()->whereNotNull('customer')->pluck('customer');
        $uniqueVehicles = Sale::select('vehicle_no')->distinct()->whereNotNull('vehicle_no')->pluck('vehicle_no');

        $accounts = Account::with('group')
            ->select('id', 'name', 'ac_number', 'group_id', 'group_code')
            ->get();

        $groupedAccounts = $accounts->groupBy(function ($account) {
            return $account->group ? $account->group->name : 'Other';
        });

        $salesHistory = Sale::select('vehicle_no', 'customer', 'product_id')
            ->whereNotNull('vehicle_no')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('vehicle_no')
            ->values();

        $closedShifts = IsShiftClose::select('close_date', 'shift_id')->get()->map(function($item) {
            return [
                'close_date' => $item->close_date->format('Y-m-d'),
                'shift_id' => $item->shift_id
            ];
        });

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'vehicles' => Vehicle::with(['customer:id,name', 'products:id,product_name'])->select('id', 'vehicle_number', 'customer_id')->get(),
            'salesHistory' => $salesHistory,
            'products' => Product::with(['unit', 'stock', 'activeRate'])->select('id', 'product_name', 'product_code', 'unit_id')->get()->map(function ($product) {
                $product->sales_price = $product->activeRate ? $product->activeRate->sales_price : 0;
                return $product;
            }),
            'shifts' => Shift::where('status', true)->select('id', 'name')->get(),
            'closedShifts' => $closedShifts,
            'uniqueCustomers' => $uniqueCustomers,
            'uniqueVehicles' => $uniqueVehicles,
            'filters' => $request->only(['search', 'customer', 'payment_status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.customer' => 'required|string',
            'products.*.mobile_number' => 'nullable|string',
            'products.*.vehicle_no' => 'required|string',
            'products.*.memo_no' => 'nullable|string|max:255',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.payment_type' => 'required|in:Cash,Bank,Mobile Bank',
            'products.*.to_account_id' => 'required|exists:accounts,id',
            'products.*.paid_amount' => 'required|numeric|min:0',
            'products.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $batchCode = BatchHelper::generateBatchCode();
            $saleIds = [];

            foreach ($request->products as $productData) {
                if (!isset($productData['product_id']) || !$productData['product_id']) {
                    continue;
                }

                $toAccount = Account::find($productData['to_account_id']);
                $product = Product::with('category')->find($productData['product_id']);
                $totalAmount = $productData['amount'] - ($productData['discount'] ?? 0);
                $categoryCode = $product->category ? $product->category->code : null;

                $transaction = null;
                if ($productData['payment_type'] !== 'Cash') {
                    $transactionId = TransactionHelper::generateTransactionId();

                    $transaction = Transaction::create([
                        'transaction_id' => $transactionId,
                        'ac_number' => $toAccount->ac_number,
                        'transaction_type' => 'Cr',
                        'amount' => $productData['paid_amount'],
                        'description' => 'Sale ' . $product->product_name . ' to ' . $productData['customer'] . ' - Batch: ' . $batchCode,
                        'payment_type' => strtolower($productData['payment_type']),
                        'bank_name' => $productData['bank_name'] ?? null,
                        'branch_name' => $productData['branch_name'] ?? null,
                        'account_number' => $productData['account_no'] ?? null,
                        'cheque_type' => $productData['bank_type'] ?? null,
                        'cheque_no' => $productData['cheque_no'] ?? null,
                        'cheque_date' => $productData['cheque_date'] ?? null,
                        'mobile_bank_name' => $productData['mobile_bank'] ?? null,
                        'mobile_number' => $productData['mobile_number'] ?? null,
                        'transaction_date' => $request->sale_date,
                        'transaction_time' => now()->format('H:i:s'),
                    ]);

                    $toAccount->increment('total_amount', $productData['paid_amount']);
                }

                $sale = Sale::create([
                    'sale_date' => $request->sale_date,
                    'sale_time' => now()->format('H:i:s'),
                    'invoice_no' => InvoiceHelper::generateInvoiceId(),
                    'memo_no' => $productData['memo_no'] ?: null,
                    'shift_id' => $request->shift_id,
                    'transaction_id' => $transaction ? $transaction->id : null,
                    'customer' => $productData['customer'],
                    'mobile_number' => $productData['mobile_number'],
                    'vehicle_no' => $productData['vehicle_no'],
                    'product_id' => $productData['product_id'],
                    'category_code' => $categoryCode,
                    'purchase_price' => $product->activeRate ? $product->activeRate->purchase_price : 0,
                    'quantity' => $productData['quantity'],
                    'amount' => $productData['amount'],
                    'discount' => $productData['discount'] ?? 0,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $productData['paid_amount'],
                    'due_amount' => 0,
                    'remarks' => $productData['remarks'],
                    'status' => true,
                ]);

                $saleIds[] = $sale->id;

                $stock = Stock::where('product_id', $productData['product_id'])->first();
                if ($stock) {
                    $stock->decrement('current_stock', $productData['quantity']);
                    $stock->decrement('available_stock', $productData['quantity']);
                }
            }

            foreach ($saleIds as $saleId) {
                SaleBatch::create([
                    'batch_code' => $batchCode,
                    'sale_id' => $saleId
                ]);
            }
        });

        return redirect()->back()->with('success', 'Sale created successfully.');
    }

    public function edit(Sale $sale)
    {
        $sale->load(['product', 'shift', 'transaction']);
        return response()->json(['sale' => $sale]);
    }

    public function update(Request $request, Sale $sale)
    {
        $request->validate([
            'sale_date' => 'required|date',
            'customer' => 'required|string',
            'mobile_number' => 'nullable|string',
            'vehicle_no' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'shift_id' => 'required|exists:shifts,id',
            'invoice_no' => 'required|string|max:255',
            'memo_no' => 'nullable|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'payment_type' => 'required|in:Cash,Bank,Mobile Bank',
            'to_account_id' => 'required|exists:accounts,id',
            'paid_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $sale) {
            if ($sale->transaction) {
                $oldAccount = Account::where('ac_number', $sale->transaction->ac_number)->first();
                if ($oldAccount) {
                    $oldAccount->decrement('total_amount', $sale->paid_amount);
                }
            }

            $oldStock = Stock::where('product_id', $sale->product_id)->first();
            if ($oldStock) {
                $oldStock->increment('current_stock', $sale->quantity);
                $oldStock->increment('available_stock', $sale->quantity);
            }

            $toAccount = Account::find($request->to_account_id);
            $product = Product::with('category')->find($request->product_id);
            $totalAmount = $request->amount - ($request->discount ?? 0);
            $categoryCode = $product->category ? $product->category->code : null;

            $transaction = null;
            if ($request->payment_type !== 'Cash') {
                $transactionId = TransactionHelper::generateTransactionId();

                $transaction = Transaction::create([
                    'transaction_id' => $transactionId,
                    'ac_number' => $toAccount->ac_number,
                    'transaction_type' => 'Cr',
                    'amount' => $request->paid_amount,
                    'description' => 'Sale ' . $product->product_name . ' to ' . $request->customer . ' - Invoice: ' . $request->invoice_no,
                    'payment_type' => strtolower($request->payment_type),
                    'bank_name' => $request->bank_name ?? null,
                    'branch_name' => $request->branch_name ?? null,
                    'account_number' => $request->account_no ?? null,
                    'cheque_type' => $request->bank_type ?? null,
                    'cheque_no' => $request->cheque_no ?? null,
                    'cheque_date' => $request->cheque_date ?? null,
                    'mobile_bank_name' => $request->mobile_bank ?? null,
                    'mobile_number' => $request->mobile_number ?? null,
                    'transaction_date' => $request->sale_date,
                    'transaction_time' => now()->format('H:i:s'),
                ]);

                $toAccount->increment('total_amount', $request->paid_amount);
            }

            $sale->update([
                'sale_date' => $request->sale_date,
                'customer' => $request->customer,
                'mobile_number' => $request->mobile_number,
                'vehicle_no' => $request->vehicle_no,
                'product_id' => $request->product_id,
                'category_code' => $categoryCode,
                'shift_id' => $request->shift_id,
                'transaction_id' => $transaction ? $transaction->id : null,
                'invoice_no' => $request->invoice_no,
                'memo_no' => $request->memo_no ?: null,
                'quantity' => $request->quantity,
                'amount' => $request->amount,
                'discount' => $request->discount ?? 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $request->paid_amount,
                'due_amount' => 0,
                'remarks' => $request->remarks,
            ]);

            if ($sale->transaction) {
                $oldTransactionId = $sale->transaction->transaction_id;
                Transaction::where('transaction_id', $oldTransactionId)->delete();
            }

            $newStock = Stock::where('product_id', $request->product_id)->first();
            if ($newStock) {
                $newStock->decrement('current_stock', $request->quantity);
                $newStock->decrement('available_stock', $request->quantity);
            }
        });

        return redirect()->back()->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            if ($sale->transaction) {
                $account = Account::where('ac_number', $sale->transaction->ac_number)->first();
                if ($account) {
                    $account->decrement('total_amount', $sale->paid_amount);
                }
                $transactionId = $sale->transaction->transaction_id;
            }

            $stock = Stock::where('product_id', $sale->product_id)->first();
            if ($stock) {
                $stock->increment('current_stock', $sale->quantity);
                $stock->increment('available_stock', $sale->quantity);
            }

            SaleBatch::where('sale_id', $sale->id)->delete();
            $sale->delete();

            if (isset($transactionId)) {
                Transaction::where('transaction_id', $transactionId)->delete();
            }
        });

        return redirect()->back()->with('success', 'Sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sales,id'
        ]);

        DB::transaction(function () use ($request) {
            $sales = Sale::with('transaction')->whereIn('id', $request->ids)->get();

            foreach ($sales as $sale) {
                if ($sale->transaction) {
                    $account = Account::where('ac_number', $sale->transaction->ac_number)->first();
                    if ($account) {
                        $account->decrement('total_amount', $sale->paid_amount);
                    }
                    $transactionId = $sale->transaction->transaction_id;
                }

                $stock = Stock::where('product_id', $sale->product_id)->first();
                if ($stock) {
                    $stock->increment('current_stock', $sale->quantity);
                    $stock->increment('available_stock', $sale->quantity);
                }

                SaleBatch::where('sale_id', $sale->id)->delete();
                $sale->delete();

                if (isset($transactionId)) {
                    Transaction::where('transaction_id', $transactionId)->delete();
                }
            }
        });

        return redirect()->back()->with('success', 'Sales deleted successfully.');
    }

    public function downloadBatchPdf($batchCode)
    {
        $saleIds = SaleBatch::where('batch_code', $batchCode)->pluck('sale_id');
        $sales = Sale::with(['product.unit', 'shift'])->whereIn('id', $saleIds)->get();

        if ($sales->isEmpty()) {
            abort(404, 'Batch not found');
        }

        $companySetting = CompanySetting::first();
        $customerGroups = $sales->groupBy('customer');

        foreach ($customerGroups as $customer => $customerSales) {
            $customerGroups[$customer]->totalInWords = NumberToWordsHelper::convert(floor($customerSales->sum('total_amount')));
        }

        $pdf = Pdf::loadView('pdf.batch-invoice', compact('customerGroups', 'batchCode', 'companySetting'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream("batch-invoice-{$batchCode}.pdf");
    }

    public function downloadPdf(Request $request)
    {
        $query = Sale::with(['product', 'shift'])
            ->leftJoin('sale_batches', 'sales.id', '=', 'sale_batches.sale_id')
            ->select('sales.*', 'sale_batches.batch_code');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                    ->orWhere('customer', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer', $request->customer);
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
            $query->where('sale_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('sale_date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $sales = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.sales', compact('sales', 'companySetting'));
        return $pdf->stream('sales.pdf');
    }
}
