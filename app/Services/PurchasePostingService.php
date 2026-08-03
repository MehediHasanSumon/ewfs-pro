<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers,
        private readonly PaymentAccountService $paymentAccounts
    ) {}

    public function createMany(array $data): array
    {
        return DB::transaction(function () use ($data) {
            return array_map(
                fn (array $product) => $this->createOne($data, $product),
                $data['products']
            );
        });
    }

    public function replace(Purchase $purchase, array $data, array $productData): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $productData) {
            $this->reverse($purchase, 'Purchase replaced from the edit workflow.');

            return $this->createOne($data, $productData);
        });
    }

    public function reverse(Purchase $purchase, string $reason = 'Purchase deleted from the workflow.'): void
    {
        DB::transaction(function () use ($purchase, $reason) {
            $purchase->loadMissing('journalEntry');

            if ($purchase->journalEntry?->status === 'posted') {
                $this->accounting->reverse($purchase->journalEntry, $reason);
            }

            $this->inventory->reverseSource(Purchase::class, $purchase->id, $reason);
        });
    }

    private function createOne(array $headerData, array $productData): Purchase
    {
        $supplier = Supplier::query()
            ->with('account.group')
            ->active()
            ->findOrFail($productData['supplier_id']);
        $product = Product::query()
            ->with(['unit', 'activeRate'])
            ->active()
            ->findOrFail($productData['product_id']);

        if (! $supplier->account || ! $supplier->account->status) {
            throw ValidationException::withMessages([
                'supplier_id' => 'The selected supplier has no active payable account.',
            ]);
        }

        if (! $product->unit) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product has no valid unit.',
            ]);
        }

        $quantity = round((float) $productData['quantity'], 6);
        $unitCost = round((float) $productData['unit_price'], 6);
        $subtotal = round($quantity * $unitCost, 4);
        $discount = round((float) ($productData['discount'] ?? 0), 4);
        $grandTotal = round($subtotal - $discount, 4);
        $paidAmount = round((float) $productData['paid_amount'], 4);
        $businessDate = $headerData['purchase_date'];
        $paymentMethod = $this->normalizePaymentMethod($productData);
        $paymentAccount = $paidAmount > 0
            ? $this->paymentAccounts->resolve(
                (int) $productData['from_account_id'],
                $productData['payment_type'],
                'from_account_id'
            )
            : null;

        if ($grandTotal <= 0) {
            throw ValidationException::withMessages([
                'discount' => 'Purchase total must be greater than zero.',
            ]);
        }

        if ($paidAmount > $grandTotal) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid amount cannot exceed the purchase total.',
            ]);
        }

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'shift_id' => $headerData['shift_id'] ?? null,
            'invoice_no' => $this->numbers->next('invoice', 'IN', $businessDate, 4),
            'supplier_invoice_no' => $headerData['supplier_invoice_no'] ?? null,
            'memo_no' => $headerData['memo_no'] ?? null,
            'purchase_date' => $businessDate,
            'purchase_time' => now()->format('H:i:s'),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'status' => 'draft',
            'remarks' => $headerData['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $item = $purchase->items()->create([
            'line_no' => 1,
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'product_code_snapshot' => $product->product_code,
            'product_name_snapshot' => $product->product_name,
            'unit_name_snapshot' => $product->unit->name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'discount_amount' => $discount,
            'tax_amount' => 0,
            'line_total' => $grandTotal,
        ]);

        $inventoryAccount = $this->systemAccounts->inventoryAsset();
        $description = 'Purchase '.$purchase->invoice_no.' from '.$supplier->name;
        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $grandTotal,
                'credit_amount' => 0,
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'description' => $description,
            ],
            [
                'account_id' => $supplier->account_id,
                'debit_amount' => 0,
                'credit_amount' => $grandTotal,
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'description' => $description,
            ],
        ];

        if ($paidAmount > 0) {
            $lines[] = [
                'account_id' => $supplier->account_id,
                'debit_amount' => $paidAmount,
                'credit_amount' => 0,
                'supplier_id' => $supplier->id,
                'payment_method' => $paymentMethod,
                'description' => 'Direct payment for '.$purchase->invoice_no,
            ];
            $lines[] = [
                'account_id' => $paymentAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $paidAmount,
                'payment_method' => $paymentMethod,
                'description' => 'Direct payment for '.$purchase->invoice_no,
            ];
        }

        $journal = $this->accounting->post([
            'shift_id' => $purchase->shift_id,
            'business_date' => $purchase->purchase_date,
            'event_type' => 'purchase',
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'reference_no' => $purchase->invoice_no,
            'description' => $description,
            'idempotency_key' => 'purchase:'.$purchase->id,
        ], $lines);

        if ($product->is_inventory_item) {
            $this->inventory->record([
                'product_id' => $product->id,
                'shift_id' => $purchase->shift_id,
                'journal_entry_id' => $journal->id,
                'business_date' => $purchase->purchase_date,
                'movement_type' => 'purchase',
                'quantity_in' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 4),
                'source_type' => Purchase::class,
                'source_id' => $purchase->id,
                'source_line_id' => $item->id,
                'idempotency_key' => 'purchase-item:'.$item->id,
                'remarks' => $purchase->remarks,
            ]);
        }

        $status = $paidAmount >= $grandTotal
            ? 'paid'
            : ($paidAmount > 0 ? 'partially_paid' : 'posted');

        $purchase->update([
            'journal_entry_id' => $journal->id,
            'status' => $status,
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $purchase->fresh(['items', 'supplier', 'journalEntry.lines.account']);
    }

    private function normalizePaymentMethod(array $data): string
    {
        if (($data['payment_type'] ?? null) === 'Bank' && ($data['bank_type'] ?? null) === 'Cheque') {
            return 'cheque';
        }

        return match ($data['payment_type'] ?? 'Cash') {
            'Bank' => 'bank',
            'Mobile Bank' => 'mobile_bank',
            default => 'cash',
        };
    }
}
