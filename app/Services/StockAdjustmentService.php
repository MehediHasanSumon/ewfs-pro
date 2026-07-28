<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts
    ) {
    }

    public function create(array $data): Stock
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()
                ->with('activeRate')
                ->findOrFail($data['product_id']);
            $targetQuantity = (float) $data['current_stock'];
            $availableQuantity = (float) $data['available_stock'];

            $this->assertAvailability($targetQuantity, $availableQuantity);

            $stock = Stock::query()->create([
                'product_id' => $product->id,
                'opening_stock' => 0,
                'current_stock' => 0,
                'reserved_stock' => 0,
                'available_stock' => 0,
                'minimum_stock' => 0,
            ]);

            if ($targetQuantity > 0) {
                $this->recordChange(
                    $stock,
                    $product,
                    $targetQuantity,
                    'opening_stock',
                    'stock-opening:'.$stock->id
                );
            }

            $stock->refresh();
            $stock->update([
                'opening_stock' => $targetQuantity,
                'reserved_stock' => $targetQuantity - $availableQuantity,
                'available_stock' => $availableQuantity,
                'refreshed_at' => now(),
            ]);

            return $stock->fresh(['product.unit', 'product.category']);
        });
    }

    public function update(Stock $stock, array $data): Stock
    {
        return DB::transaction(function () use ($stock, $data) {
            $stock = Stock::query()
                ->whereKey($stock->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $stock->product_id !== (int) $data['product_id']) {
                throw ValidationException::withMessages([
                    'product_id' => 'A stock projection cannot be moved to another product.',
                ]);
            }

            $product = Product::query()
                ->with('activeRate')
                ->findOrFail($stock->product_id);
            $targetQuantity = (float) $data['current_stock'];
            $availableQuantity = (float) $data['available_stock'];
            $delta = round(
                $targetQuantity - (float) $stock->current_stock,
                6
            );

            $this->assertAvailability($targetQuantity, $availableQuantity);

            if (abs($delta) > 0.000001) {
                $this->recordChange(
                    $stock,
                    $product,
                    $delta,
                    'manual_adjustment',
                    'stock-adjustment:'.$stock->id.':'.($stock->version + 1)
                );
            }

            $stock->refresh();
            $stock->update([
                'reserved_stock' => $targetQuantity - $availableQuantity,
                'available_stock' => $availableQuantity,
                'refreshed_at' => now(),
            ]);

            return $stock->fresh(['product.unit', 'product.category']);
        });
    }

    public function delete(Stock $stock): void
    {
        DB::transaction(function () use ($stock) {
            $stock = Stock::query()
                ->whereKey($stock->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDeletable($stock);
            $stock->delete();
        });
    }

    public function deleteMany(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $stocks = Stock::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                $this->assertDeletable($stock);
            }

            foreach ($stocks as $stock) {
                $stock->delete();
            }
        });
    }

    private function recordChange(
        Stock $stock,
        Product $product,
        float $delta,
        string $movementType,
        string $idempotencyKey
    ): void {
        $quantity = abs($delta);
        $unitCost = (float) ($product->activeRate?->purchase_price ?? 0);
        $totalCost = round($quantity * $unitCost, 4);
        $journal = $totalCost > 0
            ? $this->postJournal(
                $stock,
                $product,
                $delta,
                $totalCost,
                $movementType,
                $idempotencyKey
            )
            : null;

        $this->inventory->record([
            'product_id' => $product->id,
            'journal_entry_id' => $journal?->id,
            'business_date' => today()->toDateString(),
            'movement_type' => $movementType,
            'quantity_in' => $delta > 0 ? $quantity : 0,
            'quantity_out' => $delta < 0 ? $quantity : 0,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_type' => Stock::class,
            'source_id' => $stock->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function postJournal(
        Stock $stock,
        Product $product,
        float $delta,
        float $totalCost,
        string $movementType,
        string $idempotencyKey
    ) {
        $inventoryAccount = $this->systemAccounts->inventoryAsset();
        $offsetAccount = $movementType === 'opening_stock'
            ? $this->systemAccounts->openingBalanceEquity()
            : $this->systemAccounts->inventoryAdjustment();
        $description = $movementType === 'opening_stock'
            ? 'Opening stock for '.$product->product_name
            : 'Manual stock adjustment for '.$product->product_name;

        $lines = $delta > 0
            ? [
                [
                    'account_id' => $inventoryAccount->id,
                    'debit_amount' => $totalCost,
                    'credit_amount' => 0,
                    'product_id' => $product->id,
                    'description' => $description,
                ],
                [
                    'account_id' => $offsetAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $totalCost,
                    'product_id' => $product->id,
                    'description' => $description,
                ],
            ]
            : [
                [
                    'account_id' => $offsetAccount->id,
                    'debit_amount' => $totalCost,
                    'credit_amount' => 0,
                    'product_id' => $product->id,
                    'description' => $description,
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $totalCost,
                    'product_id' => $product->id,
                    'description' => $description,
                ],
            ];

        return $this->accounting->post([
            'business_date' => today()->toDateString(),
            'event_type' => $movementType,
            'source_type' => Stock::class,
            'source_id' => $stock->id,
            'reference_no' => 'STOCK-'.$stock->id,
            'description' => $description,
            'idempotency_key' => 'journal:'.$idempotencyKey,
        ], $lines);
    }

    private function assertAvailability(
        float $currentQuantity,
        float $availableQuantity
    ): void {
        if ($availableQuantity > $currentQuantity) {
            throw ValidationException::withMessages([
                'available_stock' => 'Available stock cannot exceed current stock.',
            ]);
        }
    }

    private function assertDeletable(Stock $stock): void
    {
        if (
            abs((float) $stock->current_stock) > 0.000001
            || abs((float) $stock->reserved_stock) > 0.000001
            || $stock->movements()->exists()
        ) {
            throw ValidationException::withMessages([
                'stock' => 'Stock with quantity or movement history cannot be deleted.',
            ]);
        }
    }
}
