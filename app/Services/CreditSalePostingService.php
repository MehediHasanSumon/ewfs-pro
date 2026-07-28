<?php

namespace App\Services;

use App\Models\CreditSale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CreditSalePostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers
    ) {
    }

    public function createMany(array $data): array
    {
        return DB::transaction(function () use ($data) {
            return array_map(
                fn (array $product) => $this->createOne($data, $product),
                $data['products']
            );
        });
    }

    public function replace(CreditSale $sale, array $data, array $productData): CreditSale
    {
        return DB::transaction(function () use ($sale, $data, $productData) {
            $this->reverse($sale, 'Credit sale replaced from the edit workflow.');

            return $this->createOne($data, $productData);
        });
    }

    public function reverse(CreditSale $sale, string $reason = 'Credit sale deleted from the workflow.'): void
    {
        DB::transaction(function () use ($sale, $reason) {
            $sale->loadMissing('customers.journalEntry');

            foreach ($sale->customers as $allocation) {
                if ($allocation->journalEntry?->status === 'posted') {
                    $this->accounting->reverse($allocation->journalEntry, $reason);
                }
            }

            $this->inventory->reverseSource(CreditSale::class, $sale->id, $reason);
        });
    }

    private function createOne(array $headerData, array $productData): CreditSale
    {
        $customer = Customer::query()->findOrFail($productData['customer_id']);
        $vehicle = Vehicle::query()->findOrFail($productData['vehicle_id']);
        $product = Product::query()
            ->with(['category', 'unit', 'activeRate'])
            ->findOrFail($productData['product_id']);

        $quantity = (float) $productData['quantity'];
        $subtotal = (float) $productData['amount'];
        $discount = (float) ($productData['discount'] ?? 0);
        $grandTotal = max(0, $subtotal - $discount);
        $unitPrice = $quantity > 0 ? $subtotal / $quantity : 0;
        $unitCost = (float) ($product->activeRate?->purchase_price ?? 0);
        $totalCost = round($quantity * $unitCost, 4);
        $businessDate = $headerData['sale_date'];

        $sale = CreditSale::query()->create([
            'shift_id' => $headerData['shift_id'],
            'sale_date' => $businessDate,
            'sale_time' => now()->format('H:i:s'),
            'invoice_no' => $this->numbers->next('invoice', 'IN', $businessDate, 4),
            'memo_no' => $productData['memo_no'] ?? $headerData['memo_no'] ?? null,
            'grand_total' => $grandTotal,
            'status' => 'draft',
            'remarks' => $productData['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $allocation = $sale->customers()->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_mobile_snapshot' => $customer->mobile,
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'grand_total' => $grandTotal,
            'status' => 'open',
        ]);

        $item = $allocation->items()->create([
            'line_no' => 1,
            'vehicle_id' => $vehicle->id,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'vehicle_number_snapshot' => $vehicle->vehicle_number,
            'product_code_snapshot' => $product->product_code,
            'product_name_snapshot' => $product->product_name,
            'category_name_snapshot' => $product->category->name,
            'unit_name_snapshot' => $product->unit->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'discount_amount' => $discount,
            'line_total' => $grandTotal,
            'remarks' => $productData['remarks'] ?? null,
        ]);

        $revenueAccount = $this->systemAccounts->salesRevenue();
        $inventoryAccount = $this->systemAccounts->inventoryAsset();
        $cogsAccount = $this->systemAccounts->costOfGoodsSold();
        $description = 'Credit sale '.$sale->invoice_no.' to '.$customer->name;
        $lines = [
            [
                'account_id' => $customer->account_id,
                'debit_amount' => $grandTotal,
                'credit_amount' => 0,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'description' => $description,
            ],
            [
                'account_id' => $revenueAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $grandTotal,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'description' => $description,
            ],
        ];

        $movementAtShiftClose = $product->category->inventory_class === 'fuel';

        if ($product->is_inventory_item && ! $movementAtShiftClose && $totalCost > 0) {
            $lines[] = [
                'account_id' => $cogsAccount->id,
                'debit_amount' => $totalCost,
                'credit_amount' => 0,
                'product_id' => $product->id,
                'description' => 'Cost of '.$description,
            ];
            $lines[] = [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $totalCost,
                'product_id' => $product->id,
                'description' => 'Inventory issued for '.$description,
            ];
        }

        $journal = $this->accounting->post([
            'shift_id' => $sale->shift_id,
            'business_date' => $sale->sale_date,
            'event_type' => 'credit_sale',
            'source_type' => CreditSale::class,
            'source_id' => $sale->id,
            'reference_no' => $sale->invoice_no,
            'description' => $description,
            'idempotency_key' => 'credit-sale-customer:'.$allocation->id,
        ], $lines);

        $allocation->update(['journal_entry_id' => $journal->id]);

        if ($product->is_inventory_item && ! $movementAtShiftClose) {
            $this->inventory->record([
                'product_id' => $product->id,
                'shift_id' => $sale->shift_id,
                'journal_entry_id' => $journal->id,
                'business_date' => $sale->sale_date,
                'movement_type' => 'credit_sale',
                'quantity_out' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'source_type' => CreditSale::class,
                'source_id' => $sale->id,
                'source_line_id' => $item->id,
                'idempotency_key' => 'credit-sale-item:'.$item->id,
            ]);
        }

        $sale->update([
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $sale->fresh(['customers.customer', 'customers.items.product', 'customers.journalEntry.lines']);
    }
}
