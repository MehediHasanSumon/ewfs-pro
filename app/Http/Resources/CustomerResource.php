<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'code' => $this->code,
            'name' => $this->name,
            'proprietor_name' => $this->proprietor_name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'nid_number' => $this->nid_number,
            'vat_reg_no' => $this->vat_reg_no,
            'tin_no' => $this->tin_no,
            'trade_license' => $this->trade_license,
            'discount_rate' => (float) $this->discount_rate,
            'credit_limit' => (float) $this->credit_limit,
            'address' => $this->address,
            'status' => (bool) $this->status,
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account?->id,
                'name' => $this->account?->name,
                'ac_number' => $this->account?->ac_number,
            ]),
            'vehicles' => $this->whenLoaded(
                'vehicles',
                fn () => VehicleResource::collection($this->vehicles)
                    ->resolve($request)
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
