<?php

namespace App\Http\Controllers;

use App\Models\WhiteSale;
use App\Models\WhiteSaleProduct;
use App\Models\Shift;
use App\Models\Product;
use App\Models\CompanySetting;
use App\Helpers\InvoiceHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class WhiteSaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-white-sale', only: ['index', 'getCustomerByMobile']),
            new Middleware('permission:view-white-sale|can-white-sale-download', only: ['downloadSinglePdf']),
            new Middleware('permission:create-white-sale', only: ['store']),
            new Middleware('permission:update-white-sale', only: ['update']),
            new Middleware('permission:delete-white-sale', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = WhiteSale::with(['shift', 'products.product', 'products.category', 'products.unit']);

        if ($request->search) {
            $query->whereDate('sale_date', 'like', '%' . $request->search . '%');
        }

        if ($request->start_date) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sortBy = $request->sort_by ?? 'sale_date';
        $sortOrder = $request->sort_order ?? 'desc';
        $perPage = $request->per_page ?? 10;

        $whiteSales = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        $shifts = Shift::where('status', 1)->get();
        $products = Product::with(['category', 'unit', 'activeRate', 'rates'])->where('status', 1)->get();

        return Inertia::render('WhiteSales/Index', [
            'whiteSales' => $whiteSales,
            'shifts' => $shifts,
            'products' => $products,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'mobile_no' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
            'proprietor_name' => 'nullable|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.product' => 'required|string',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'sometimes|boolean',
            'is_send_sms' => 'sometimes|boolean'
        ]);

        $whiteSale = WhiteSale::create([
            'sale_date' => now()->toDateString(),
            'sale_time' => now()->toTimeString(),
            'invoice_no' => InvoiceHelper::generateInvoiceId(),
            'shift_id' => $request->shift_id,
            'mobile_no' => $request->mobile_no,
            'company_name' => $request->company_name,
            'proprietor_name' => $request->proprietor_name,
            'total_amount' => $request->total_amount,
            'remarks' => $request->remarks,
            'status' => $request->status ?? true,
            'is_send_sms' => $request->is_send_sms ?? false
        ]);

        foreach ($request->products as $productData) {
            $product = Product::where('product_name', $productData['product'])->first();
            if ($product) {
                WhiteSaleProduct::create([
                    'white_sale_id' => $whiteSale->id,
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'unit_id' => $product->unit_id,
                    'quantity' => $productData['quantity'],
                    'sales_price' => $productData['purchase_price'],
                    'amount' => $productData['amount']
                ]);
            }
        }

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale created successfully.');
    }

    public function update(Request $request, WhiteSale $whiteSale)
    {
        $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'mobile_no' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
            'proprietor_name' => 'nullable|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.product' => 'required|string',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'sometimes|boolean',
            'is_send_sms' => 'sometimes|boolean'
        ]);

        $whiteSale->update([
            'shift_id' => $request->shift_id,
            'mobile_no' => $request->mobile_no,
            'company_name' => $request->company_name,
            'proprietor_name' => $request->proprietor_name,
            'total_amount' => $request->total_amount,
            'remarks' => $request->remarks,
            'status' => $request->status ?? true,
            'is_send_sms' => $request->is_send_sms ?? false
        ]);

        $whiteSale->products()->delete();

        foreach ($request->products as $productData) {
            $product = Product::where('product_name', $productData['product'])->first();
            if ($product) {
                WhiteSaleProduct::create([
                    'white_sale_id' => $whiteSale->id,
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'unit_id' => $product->unit_id,
                    'quantity' => $productData['quantity'],
                    'sales_price' => $productData['purchase_price'],
                    'amount' => $productData['amount']
                ]);
            }
        }

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale updated successfully.');
    }

    public function downloadSinglePdf(WhiteSale $whiteSale)
    {
        $whiteSale->load(['shift', 'products.product', 'products.category', 'products.unit']);
        $companySetting = CompanySetting::first();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('pdf.white-sale-invoice', compact('whiteSale', 'companySetting'));

        return $pdf->stream('white-sale-' . $whiteSale->invoice_no . '.pdf');
    }

    public function getCustomerByMobile($mobile)
    {
        $customer = WhiteSale::where('mobile_no', $mobile)
            ->select('company_name', 'proprietor_name')
            ->first();

        return response()->json($customer);
    }

    public function destroy(WhiteSale $whiteSale)
    {
        $whiteSale->products()->delete();
        $whiteSale->delete();

        return redirect()->route('white-sales.index')
            ->with('success', 'White sale deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:white_sales,id'
        ]);

        WhiteSaleProduct::whereIn('white_sale_id', $request->ids)->delete();
        WhiteSale::whereIn('id', $request->ids)->delete();

        return redirect()->route('white-sales.index')
            ->with('success', 'Selected white sales deleted successfully.');
    }
}
