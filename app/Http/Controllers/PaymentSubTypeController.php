<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubType;
use App\Models\VoucherCategory;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class PaymentSubTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-payment-sub-type', only: ['index']),
            new Middleware('permission:view-payment-sub-type|can-payment-sub-type-download', only: ['downloadPdf']),
            new Middleware('permission:create-payment-sub-type', only: ['store']),
            new Middleware('permission:update-payment-sub-type', only: ['edit', 'update']),
            new Middleware('permission:delete-payment-sub-type', only: ['destroy', 'bulkDelete']),
        ];
    }
    public function index(Request $request)
    {
        $query = PaymentSubType::select('id', 'code', 'name', 'voucher_category_id', 'type', 'status', 'created_at')
            ->with('voucherCategory:id,name');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->category && $request->category !== 'all') {
            $query->where('voucher_category_id', $request->category);
        }

        if ($request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === '1');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $paymentSubTypes = $query->paginate($perPage)->withQueryString()->through(function ($paymentSubType) {
            return [
                'id' => $paymentSubType->id,
                'code' => $paymentSubType->code,
                'name' => $paymentSubType->name,
                'voucher_category' => $paymentSubType->voucherCategory->name ?? 'N/A',
                'voucher_category_id' => $paymentSubType->voucher_category_id,
                'type' => $paymentSubType->type,
                'status' => $paymentSubType->status,
                'created_at' => $paymentSubType->created_at->format('Y-m-d'),
            ];
        });

        $voucherCategories = VoucherCategory::where('status', true)->get(['id', 'name']);

        return Inertia::render('PaymentSubTypes/PaymentSubType', [
            'paymentSubTypes' => $paymentSubTypes,
            'voucherCategories' => $voucherCategories,
            'filters' => $request->only(['search', 'category', 'type', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:payment_sub_types,code',
            'name' => 'required|string|max:255',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'type' => 'required|in:payment,receipt,both',
            'status' => 'boolean'
        ]);

        PaymentSubType::create([
            'code' => $request->code,
            'name' => $request->name,
            'voucher_category_id' => $request->voucher_category_id,
            'type' => $request->type,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Payment sub type created successfully.');
    }

    public function edit(PaymentSubType $paymentSubType)
    {
        return response()->json([
            'paymentSubType' => $paymentSubType
        ]);
    }

    public function update(Request $request, PaymentSubType $paymentSubType)
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:payment_sub_types,code,' . $paymentSubType->id,
            'name' => 'required|string|max:255',
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'type' => 'required|in:payment,receipt,both',
            'status' => 'boolean'
        ]);

        $paymentSubType->update([
            'code' => $request->code,
            'name' => $request->name,
            'voucher_category_id' => $request->voucher_category_id,
            'type' => $request->type,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Payment sub type updated successfully.');
    }

    public function destroy(PaymentSubType $paymentSubType)
    {
        $paymentSubType->delete();
        return redirect()->back()->with('success', 'Payment sub type deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:payment_sub_types,id'
        ]);

        PaymentSubType::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' payment sub types deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = PaymentSubType::select('id', 'code', 'name', 'voucher_category_id', 'type', 'status', 'created_at')
            ->with('voucherCategory:id,name');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->category && $request->category !== 'all') {
            $query->where('voucher_category_id', $request->category);
        }

        if ($request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === '1');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $paymentSubTypes = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.payment-sub-types', compact('paymentSubTypes', 'companySetting'));
        return $pdf->stream('payment-sub-types.pdf');
    }
}
