<?php

namespace App\Http\Controllers;

use App\Models\CreditSale;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\CompanySetting;
use App\Models\IsShiftClose;
use App\Helpers\InvoiceHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CreditSaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-credit-sale', only: ['index']),
            new Middleware('permission:view-credit-sale|can-credit-sale-download', only: ['downloadPdf']),
            new Middleware('permission:create-credit-sale', only: ['store']),
            new Middleware('permission:update-credit-sale', only: ['edit', 'update']),
            new Middleware('permission:delete-credit-sale', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = CreditSale::with(['product', 'shift', 'customer', 'vehicle'])
            ->where('type', 'regular');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer_id', $request->customer);
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

        $creditSales = $query->paginate($request->per_page ?? 10);

        $closedShifts = IsShiftClose::select('close_date', 'shift_id')->get()->map(function($item) {
            return [
                'close_date' => $item->close_date->format('Y-m-d'),
                'shift_id' => $item->shift_id
            ];
        });

        return Inertia::render('CreditSales/Index', [
            'creditSales' => $creditSales,
            'vehicles' => Vehicle::with(['customer:id,name', 'products:id,product_name'])->select('id', 'vehicle_number', 'customer_id')->get(),
            'customers' => Customer::where('status', true)->select('id', 'name')->get(),
            'products' => Product::with('activeRate')->select('id', 'product_name')->get()->map(function($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'sales_price' => $product->activeRate ? (float) $product->activeRate->sales_price : 0
                ];
            }),
            'shifts' => Shift::where('status', true)->select('id', 'name')->get(),
            'closedShifts' => $closedShifts,
            'filters' => $request->only(['search', 'customer', 'payment_status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'memo_no' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.customer_id' => 'required|exists:customers,id',
            'products.*.vehicle_id' => 'required|exists:vehicles,id',
            'products.*.memo_no' => 'nullable|string',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.due_amount' => 'required|numeric|min:0',
            'products.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $invoiceNo = InvoiceHelper::generateInvoiceId();
            
            foreach ($request->products as $productData) {
                if (!isset($productData['product_id']) || !$productData['product_id']) {
                    continue;
                }

                $product = Product::with('category')->find($productData['product_id']);
                $amount = $productData['amount'];
                $discount = $productData['discount'] ?? 0;
                $totalAmount = $amount - $discount;
                $dueAmount = $productData['due_amount'];
                $categoryCode = $product->category ? $product->category->code : null;

                CreditSale::create([
                    'sale_date' => $request->sale_date,
                    'sale_time' => now()->format('H:i:s'),
                    'invoice_no' => $invoiceNo,
                    'shift_id' => $request->shift_id,
                    'transaction_id' => null,
                    'customer_id' => $productData['customer_id'],
                    'vehicle_id' => $productData['vehicle_id'],
                    'product_id' => $productData['product_id'],
                    'category_code' => $categoryCode,
                    'type' => 'regular',
                    'purchase_price' => $product->activeRate ? $product->activeRate->purchase_price : 0,
                    'quantity' => $productData['quantity'],
                    'amount' => $amount,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'due_amount' => $dueAmount,
                    'memo_no' => $productData['memo_no'] ?? $request->memo_no,
                    'remarks' => $productData['remarks'],
                ]);

                $stock = Stock::where('product_id', $productData['product_id'])->first();
                if ($stock) {
                    $stock->decrement('current_stock', $productData['quantity']);
                    $stock->decrement('available_stock', $productData['quantity']);
                }
            }
        });

        return redirect()->back()->with('success', 'Credit sale created successfully.');
    }

    public function edit(CreditSale $creditSale)
    {
        if ($creditSale->type !== 'regular') {
            abort(404);
        }
        $creditSale->load(['product', 'shift', 'customer', 'vehicle', 'transaction']);
        return response()->json(['creditSale' => $creditSale]);
    }

    public function update(Request $request, CreditSale $creditSale)
    {
        if ($creditSale->type !== 'regular') {
            abort(404);
        }
        $request->validate([
            'sale_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'product_id' => 'required|exists:products,id',
            'shift_id' => 'required|exists:shifts,id',
            'memo_no' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $creditSale) {
            $oldStock = Stock::where('product_id', $creditSale->product_id)->first();
            if ($oldStock) {
                $oldStock->increment('current_stock', $creditSale->quantity);
                $oldStock->increment('available_stock', $creditSale->quantity);
            }

            $product = Product::with('category')->find($request->product_id);
            $amount = $request->amount;
            $discount = $request->discount ?? 0;
            $totalAmount = $amount - $discount;
            $dueAmount = $request->due_amount;
            $categoryCode = $product->category ? $product->category->code : null;

            $creditSale->update([
                'sale_date' => $request->sale_date,
                'customer_id' => $request->customer_id,
                'vehicle_id' => $request->vehicle_id,
                'product_id' => $request->product_id,
                'category_code' => $categoryCode,
                'shift_id' => $request->shift_id,
                'quantity' => $request->quantity,
                'amount' => $amount,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'due_amount' => $dueAmount,
                'memo_no' => $request->memo_no,
                'remarks' => $request->remarks,
            ]);

            $newStock = Stock::where('product_id', $request->product_id)->first();
            if ($newStock) {
                $newStock->decrement('current_stock', $request->quantity);
                $newStock->decrement('available_stock', $request->quantity);
            }
        });

        return redirect()->back()->with('success', 'Credit sale updated successfully.');
    }

    public function destroy(CreditSale $creditSale)
    {
        if ($creditSale->type !== 'regular') {
            abort(404);
        }
        DB::transaction(function () use ($creditSale) {
            $stock = Stock::where('product_id', $creditSale->product_id)->first();
            if ($stock) {
                $stock->increment('current_stock', $creditSale->quantity);
                $stock->increment('available_stock', $creditSale->quantity);
            }

            $creditSale->delete();
        });

        return redirect()->back()->with('success', 'Credit sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:credit_sales,id'
        ]);

        DB::transaction(function () use ($request) {
            $creditSales = CreditSale::whereIn('id', $request->ids)->where('type', 'regular')->get();

            foreach ($creditSales as $creditSale) {
                $stock = Stock::where('product_id', $creditSale->product_id)->first();
                if ($stock) {
                    $stock->increment('current_stock', $creditSale->quantity);
                    $stock->increment('available_stock', $creditSale->quantity);
                }

                $creditSale->delete();
            }
        });

        return redirect()->back()->with('success', 'Credit sales deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = CreditSale::with(['product', 'shift', 'customer', 'vehicle'])
            ->where('type', 'regular');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('invoice_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->customer && $request->customer !== 'all') {
            $query->where('customer_id', $request->customer);
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

        $creditSales = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.credit-sales', compact('creditSales', 'companySetting'));
        return $pdf->stream();
    }
}
