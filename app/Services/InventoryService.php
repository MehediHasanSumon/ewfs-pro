<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function record(array $data): InventoryMovement
    {
        return $this->recordMany([$data])->firstOrFail();
    }

    public function recordMany(array $movements): Collection
    {
        if ($movements === []) {
            return collect();
        }

        return DB::transaction(function () use ($movements) {
            $normalized = collect($movements)
                ->values()
                ->map(fn (array $movement) => $this->normalize($movement));
            $existing = InventoryMovement::query()
                ->whereIn(
                    'idempotency_key',
                    $normalized->pluck('idempotency_key')
                )
                ->get()
                ->keyBy('idempotency_key');
            $pending = $normalized
                ->unique('idempotency_key')
                ->reject(fn (array $movement) => $existing
                    ->has($movement['idempotency_key']));

            if ($pending->isEmpty()) {
                return $normalized
                    ->map(fn (array $movement) => $existing
                        ->get($movement['idempotency_key']));
            }

            $stocks = $this->lockStocks(
                $pending->pluck('product_id')->unique()->all()
            );
            $concurrentExisting = InventoryMovement::query()
                ->whereIn(
                    'idempotency_key',
                    $pending->pluck('idempotency_key')
                )
                ->get()
                ->keyBy('idempotency_key');
            $existing = $existing->union($concurrentExisting);
            $pending = $pending->reject(fn (array $movement) => $existing
                ->has($movement['idempotency_key']));
            $created = collect();

            foreach ($pending as $movementData) {
                $stock = $stocks->get($movementData['product_id']);
                $beforeStock = (float) $stock->current_stock;
                $afterStock = round(
                    $beforeStock
                    + $movementData['quantity_in']
                    - $movementData['quantity_out'],
                    6
                );
                $afterAvailable = round(
                    $afterStock - (float) $stock->reserved_stock,
                    6
                );

                if (
                    $afterStock < 0
                    || $afterAvailable < 0
                ) {
                    $this->throwInsufficientStock();
                }

                $movement = InventoryMovement::query()->create([
                    ...$movementData,
                    'before_stock' => $beforeStock,
                    'after_stock' => max(0, $afterStock),
                ]);

                $stock->fill([
                    'current_stock' => max(0, $afterStock),
                    'available_stock' => max(0, $afterAvailable),
                    'last_movement_id' => $movement->id,
                    'version' => $stock->version + 1,
                    'refreshed_at' => now(),
                ])->save();

                $created->put($movementData['idempotency_key'], $movement);
            }

            return $normalized->map(
                fn (array $movement) => $existing
                    ->get($movement['idempotency_key'])
                    ?? $created->get($movement['idempotency_key'])
            );
        });
    }

    public function assertAvailable(array $requirements): void
    {
        $requiredByProduct = collect($requirements)
            ->mapWithKeys(fn ($quantity, $productId) => [
                (int) $productId => round((float) $quantity, 6),
            ])
            ->filter(fn (float $quantity) => $quantity > 0)
            ->groupBy(fn (float $quantity, int $productId) => $productId)
            ->map(fn (Collection $quantities) => round(
                (float) $quantities->sum(),
                6
            ));

        if ($requiredByProduct->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($requiredByProduct) {
            $stocks = $this->lockStocks($requiredByProduct->keys()->all());

            foreach ($requiredByProduct as $productId => $quantity) {
                if (
                    (float) $stocks->get($productId)->available_stock
                    + 0.000001
                    < $quantity
                ) {
                    $this->throwInsufficientStock();
                }
            }
        });
    }

    public function reverseSource(string $sourceType, int $sourceId, string $reason): void
    {
        $reversals = InventoryMovement::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNull('reversal_of_id')
            ->orderBy('product_id')
            ->orderBy('id')
            ->get()
            ->map(fn (InventoryMovement $movement) => [
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
                'remarks' => $reason,
            ])
            ->all();

        $this->recordMany($reversals);
    }

    private function lockStocks(array $productIds): Collection
    {
        $productIds = collect($productIds)
            ->map(fn ($productId) => (int) $productId)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $now = now();

        DB::table('stocks')->insertOrIgnore(
            $productIds->map(fn (int $productId) => [
                'product_id' => $productId,
                'opening_stock' => 0,
                'current_stock' => 0,
                'reserved_stock' => 0,
                'available_stock' => 0,
                'minimum_stock' => 0,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        return Stock::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    private function normalize(array $data): array
    {
        $quantityIn = round((float) ($data['quantity_in'] ?? 0), 6);
        $quantityOut = round((float) ($data['quantity_out'] ?? 0), 6);

        if (
            ($quantityIn > 0 && $quantityOut > 0)
            || ($quantityIn <= 0 && $quantityOut <= 0)
        ) {
            throw ValidationException::withMessages([
                'stock' => 'An inventory movement requires one positive quantity direction.',
            ]);
        }

        return [
            'product_id' => (int) $data['product_id'],
            'shift_id' => $data['shift_id'] ?? null,
            'journal_entry_id' => $data['journal_entry_id'] ?? null,
            'business_date' => $data['business_date'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'movement_type' => $data['movement_type'],
            'quantity_in' => max(0, $quantityIn),
            'quantity_out' => max(0, $quantityOut),
            'unit_cost' => max(0, (float) ($data['unit_cost'] ?? 0)),
            'total_cost' => max(0, (float) ($data['total_cost'] ?? 0)),
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'] ?? null,
            'source_line_id' => $data['source_line_id'] ?? null,
            'reversal_of_id' => $data['reversal_of_id'] ?? null,
            'idempotency_key' => $data['idempotency_key'],
            'posted_by' => $data['posted_by'] ?? auth()->id(),
            'remarks' => $data['remarks'] ?? $data['description'] ?? null,
        ];
    }

    private function throwInsufficientStock(): never
    {
        throw ValidationException::withMessages([
            'stock' => 'Insufficient stock available.',
        ]);
    }
}
