<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'month' => $this->month,
            'year' => $this->year,
            'label' => $this->label(),
            'status' => $this->status,
            'payable_date' => $this->payable_date?->format('Y-m-d'),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'locked_at' => $this->locked_at?->toISOString(),
            'snapshot_count' => $this->whenCounted('snapshots'),
            'item_count' => $this->whenCounted('items'),
            'pending_count' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->where('status', 'pending')->count()
            ),
        ];
    }
}
