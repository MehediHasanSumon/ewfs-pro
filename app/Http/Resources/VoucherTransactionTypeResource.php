<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherTransactionTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_category_id' => $this->voucher_category_id,
            'code' => $this->code,
            'name' => $this->name,
            'voucher_type' => $this->voucher_type,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'is_system' => $this->isSystemType(),
            'voucher_category' => $this->whenLoaded('voucherCategory', fn () => [
                'id' => $this->voucherCategory?->id,
                'code' => $this->voucherCategory?->code,
                'name' => $this->voucherCategory?->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
