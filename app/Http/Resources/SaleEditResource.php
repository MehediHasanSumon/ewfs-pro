<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleEditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paymentDetail = $this->relationLoaded('paymentDetail')
            ? $this->paymentDetail
            : null;
        $paymentLine = $this->relationLoaded('transaction')
            ? $this->transaction
            : null;

        return [
            'id' => $this->id,
            'sale_date' => $this->sale_date?->format('Y-m-d'),
            'shift_id' => $this->shift_id,
            'invoice_no' => $this->invoice_no,
            'memo_no' => $this->memo_no,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name_snapshot,
            'customer_mobile' => $this->customer_mobile_snapshot,
            'customer_address' => $this->customer_address_snapshot,
            'vehicle_id' => $this->vehicle_id,
            'vehicle_no' => $this->vehicle_number_snapshot,
            'remarks' => $this->remarks,
            'grand_total' => (float) $this->grand_total,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name_snapshot,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount_amount,
                'line_total' => (float) $item->line_total,
                'remarks' => $item->remarks,
            ])->values()->all(),
            'payment' => [
                'payment_type' => $paymentDetail?->payment_method
                    ?? $paymentLine?->payment_method,
                'to_account_id' => $paymentDetail?->account_id
                    ?? $paymentLine?->account_id,
                'paid_amount' => (float) $this->grand_total,
                'bank_type' => $paymentDetail?->bank_type,
                'bank_name' => $paymentDetail?->bank_name,
                'branch_name' => $paymentDetail?->branch_name,
                'account_no' => $paymentDetail?->account_number
                    ?? $paymentLine?->account?->ac_number,
                'cheque_no' => $paymentDetail?->cheque_number,
                'cheque_date' => $paymentDetail?->cheque_date?->format('Y-m-d'),
                'mobile_bank' => $paymentDetail?->mobile_bank_name,
                'payment_mobile_number' => $paymentDetail?->mobile_number,
            ],
        ];
    }
}
