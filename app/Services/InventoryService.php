<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function record(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $existing = InventoryMovement::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $quantityIn = (float) ($data['quantity_in'] ?? 0);
            $quantityOut = (float) ($data['quantity_out'] ?? 0);

            Stock::query()->firstOrCreate(
                ['product_id' => $data['product_id']],
                [
                    'opening_stock' => 0,
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'available_stock' => 0,
                    'minimum_stock' => 0,
                ]
            );

            $stock = Stock::query()
                ->where('product_id', $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $newCurrent = (float) $stock->current_stock + $quantityIn - $quantityOut;

            if ($newCurrent < 0) {
                throw ValidationException::withMessages([
                    'stock' => 'Insufficient stock for product '.$data['product_id'].'.',
                ]);
            }

            $movement = InventoryMovement::query()->create([
                'product_id' => $data['product_id'],
                'shift_id' => $data['shift_id'] ?? null,
                'journal_entry_id' => $data['journal_entry_id'] ?? null,
                'business_date' => $data['business_date'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'movement_type' => $data['movement_type'],
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'unit_cost' => $data['unit_cost'] ?? 0,
                'total_cost' => $data['total_cost'] ?? 0,
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'source_line_id' => $data['source_line_id'] ?? null,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'posted_by' => $data['posted_by'] ?? auth()->id(),
            ]);

            $stock->update([
                'current_stock' => $newCurrent,
                'available_stock' => max(0, $newCurrent - (float) $stock->reserved_stock),
                'last_movement_id' => $movement->id,
                'version' => $stock->version + 1,
                'refreshed_at' => now(),
            ]);

            return $movement;
        });
    }

    public function reverseSource(string $sourceType, int $sourceId, string $reason): void
    {
        InventoryMovement::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNull('reversal_of_id')
            ->orderBy('id')
            ->get()
            ->each(function (InventoryMovement $movement) use ($reason) {
                $this->record([
                    'product_id' => $movement->product_id,
                    'shift_id' => $movement->shift_id,
                    'journal_entry_id' => null,
                    'business_date' => now()->toDateString(),
                    'movement_type' => $movement->movement_type.'_reversal',
                    'quantity_in' => $movement->quantity_out,
                    'quantity_out' => $movement->quantity_in,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost' => $movement->total_cost,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_line_id' => $movement->source_line_id,
                    'reversal_of_id' => $movement->id,
                    'idempotency_key' => 'inventory-reversal:'.$movement->id,
                    'description' => $reason,
                ]);
            });
    }
}
