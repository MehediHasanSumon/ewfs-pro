<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleBatch;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalePostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers
    ) {
    }

    public function createMany(array $data, string $saleType = 'regular'): array
    {
        return DB::transaction(function () use ($data, $saleType) {
            $batchCode = 'BATCH'.Str::upper(Str::random(10));
            $sales = [];

            foreach ($data['products'] as $productData) {
                $sales[] = $this->createOne($data, $productData, $saleType, $batchCode);
            }

            return $sales;
        });
    }

    public function replace(Sale $sale, array $data, array $productData): Sale
    {
        return DB::transaction(function () use ($sale, $data, $productData) {
            $this->reverse($sale, 'Sale replaced from the edit workflow.');

            return $this->createOne(
                $data,
                $productData,
                $sale->sale_type,
                $sale->batch?->batch_code ?? 'BATCH'.Str::upper(Str::random(10))
            );
        });
    }

    public function createWhiteSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $businessDate = $data['sale_date'] ?? now()->toDateString();
            $resolvedProducts = collect($data['products'])->map(function (array $line) {
                $product = Product::query()
                    ->with(['category', 'unit', 'activeRate'])
                    ->where('product_name', $line['product'])
                    ->firstOrFail();

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['purchase_price'];
                $lineTotal = round($quantity * $unitPrice, 4);

                return compact('line', 'product', 'quantity', 'unitPrice', 'lineTotal');
            });
            $grandTotal = round((float) $resolvedProducts->sum('lineTotal'), 4);

            $sale = Sale::query()->create([
                'shift_id' => $data['shift_id'],
                'sale_type' => 'white',
                'sale_date' => $businessDate,
                'sale_time' => now()->format('H:i:s'),
                'invoice_no' => $this->numbers->next('invoice', 'IN', $businessDate, 4),
                'customer_name_snapshot' => $data['company_name'],
                'customer_mobile_snapshot' => $data['mobile_no'],
                'company_name_snapshot' => $data['company_name'],
                'proprietor_name_snapshot' => $data['proprietor_name'] ?? null,
                'subtotal' => $grandTotal,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'status' => 'draft',
                'is_send_sms' => $data['is_send_sms'] ?? false,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $revenueAccount = $this->systemAccounts->salesRevenue();
            $inventoryAccount = $this->systemAccounts->inventoryAsset();
            $cogsAccount = $this->systemAccounts->costOfGoodsSold();
            $cashAccount = $this->systemAccounts->cashOnHand();
            $journalLines = [
                [
                    'account_id' => $cashAccount->id,
                    'debit_amount' => $grandTotal,
                    'credit_amount' => 0,
                    'payment_method' => 'cash',
                    'description' => 'White sale '.$sale->invoice_no,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $grandTotal,
                    'description' => 'White sale '.$sale->invoice_no,
                ],
            ];
            $movementItems = [];

            foreach ($resolvedProducts as $index => $resolved) {
                $product = $resolved['product'];
                $unitCost = (float) ($product->activeRate?->purchase_price ?? 0);
                $totalCost = round($resolved['quantity'] * $unitCost, 4);
                $item = $sale->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'unit_id' => $product->unit_id,
                    'product_code_snapshot' => $product->product_code,
                    'product_name_snapshot' => $product->product_name,
                    'category_name_snapshot' => $product->category->name,
                    'unit_name_snapshot' => $product->unit->name,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $resolved['unitPrice'],
                    'unit_cost' => $unitCost,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'line_total' => $resolved['lineTotal'],
                ]);

                $movementAtShiftClose = $product->category->inventory_class === 'fuel';
                $movementItems[] = compact(
                    'item',
                    'product',
                    'unitCost',
                    'totalCost',
                    'movementAtShiftClose'
                ) + [
                    'quantity' => $resolved['quantity'],
                ];

                if ($product->is_inventory_item && ! $movementAtShiftClose && $totalCost > 0) {
                    $journalLines[] = [
                        'account_id' => $cogsAccount->id,
                        'debit_amount' => $totalCost,
                        'credit_amount' => 0,
                        'product_id' => $product->id,
                        'description' => 'Cost of white sale '.$sale->invoice_no,
                    ];
                    $journalLines[] = [
                        'account_id' => $inventoryAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $totalCost,
                        'product_id' => $product->id,
                        'description' => 'Inventory issued for white sale '.$sale->invoice_no,
                    ];
                }
            }

            $journal = $this->accounting->post([
                'shift_id' => $sale->shift_id,
                'business_date' => $sale->sale_date,
                'event_type' => 'white_sale',
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'reference_no' => $sale->invoice_no,
                'description' => 'White sale '.$sale->invoice_no,
                'idempotency_key' => 'white-sale:'.$sale->id,
            ], $journalLines);

            foreach ($movementItems as $movementItem) {
                if (! $movementItem['product']->is_inventory_item || $movementItem['movementAtShiftClose']) {
                    continue;
                }

                $this->inventory->record([
                    'product_id' => $movementItem['product']->id,
                    'shift_id' => $sale->shift_id,
                    'journal_entry_id' => $journal->id,
                    'business_date' => $sale->sale_date,
                    'movement_type' => 'white_sale',
                    'quantity_out' => $movementItem['quantity'],
                    'unit_cost' => $movementItem['unitCost'],
                    'total_cost' => $movementItem['totalCost'],
                    'source_type' => Sale::class,
                    'source_id' => $sale->id,
                    'source_line_id' => $movementItem['item']->id,
                    'idempotency_key' => 'white-sale-item:'.$movementItem['item']->id,
                ]);
            }

            $sale->update([
                'journal_entry_id' => $journal->id,
                'status' => 'paid',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $sale->fresh(['items.product', 'items.category', 'items.unit', 'shift']);
        });
    }

    public function replaceWhiteSale(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $this->reverse($sale, 'White sale replaced from the edit workflow.');

            return $this->createWhiteSale($data);
        });
    }

    public function reverse(Sale $sale, string $reason = 'Sale deleted from the workflow.'): void
    {
        DB::transaction(function () use ($sale, $reason) {
            $sale->loadMissing('journalEntry');

            if ($sale->journalEntry?->status === 'posted') {
                $this->accounting->reverse($sale->journalEntry, $reason);
            }

            $this->inventory->reverseSource(Sale::class, $sale->id, $reason);
        });
    }

    private function createOne(
        array $headerData,
        array $productData,
        string $saleType,
        string $batchCode
    ): Sale {
        $product = Product::query()
            ->with(['category', 'unit', 'activeRate'])
            ->findOrFail($productData['product_id']);

        $quantity = (float) $productData['quantity'];
        $grossAmount = (float) $productData['amount'];
        $discount = (float) ($productData['discount'] ?? 0);
        $grandTotal = max(0, $grossAmount - $discount);
        $unitPrice = $quantity > 0 ? $grossAmount / $quantity : 0;
        $unitCost = (float) ($product->activeRate?->purchase_price ?? 0);
        $totalCost = round($quantity * $unitCost, 4);
        $businessDate = $headerData['sale_date'] ?? now()->toDateString();
        $paymentAccountId = (int) $productData['to_account_id'];
        $paymentMethod = $this->normalizePaymentMethod($productData);
        $customer = $this->resolveCustomer($productData);
        $vehicle = $this->resolveVehicle($productData, $customer?->id);

        $sale = Sale::query()->create([
            'shift_id' => $headerData['shift_id'],
            'customer_id' => $customer?->id,
            'vehicle_id' => $vehicle?->id,
            'sale_type' => $saleType,
            'sale_date' => $businessDate,
            'sale_time' => now()->format('H:i:s'),
            'invoice_no' => $this->numbers->next('invoice', 'IN', $businessDate, 4),
            'memo_no' => $productData['memo_no'] ?? $headerData['memo_no'] ?? null,
            'customer_name_snapshot' => $productData['customer']
                ?? $headerData['company_name']
                ?? null,
            'customer_mobile_snapshot' => $productData['mobile_number']
                ?? $headerData['mobile_no']
                ?? null,
            'company_name_snapshot' => $headerData['company_name'] ?? null,
            'proprietor_name_snapshot' => $headerData['proprietor_name'] ?? null,
            'vehicle_number_snapshot' => $productData['vehicle_no'] ?? null,
            'subtotal' => $grossAmount,
            'discount_total' => $discount,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'status' => 'draft',
            'is_send_sms' => $headerData['is_send_sms'] ?? false,
            'remarks' => $productData['remarks'] ?? $headerData['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $item = $sale->items()->create([
            'line_no' => 1,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'product_code_snapshot' => $product->product_code,
            'product_name_snapshot' => $product->product_name,
            'category_name_snapshot' => $product->category->name,
            'unit_name_snapshot' => $product->unit->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'discount_amount' => $discount,
            'tax_amount' => 0,
            'line_total' => $grandTotal,
            'remarks' => $productData['remarks'] ?? null,
        ]);

        $revenueAccount = $this->systemAccounts->salesRevenue();
        $inventoryAccount = $this->systemAccounts->inventoryAsset();
        $cogsAccount = $this->systemAccounts->costOfGoodsSold();
        $description = ucfirst($saleType).' sale '.$sale->invoice_no;
        $journalLines = [
            [
                'account_id' => $paymentAccountId,
                'debit_amount' => $grandTotal,
                'credit_amount' => 0,
                'customer_id' => $customer?->id,
                'product_id' => $product->id,
                'payment_method' => $paymentMethod,
                'description' => $description,
            ],
            [
                'account_id' => $revenueAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $grandTotal,
                'customer_id' => $customer?->id,
                'product_id' => $product->id,
                'description' => $description,
            ],
        ];

        $movementAtShiftClose = $product->category->inventory_class === 'fuel';

        if ($product->is_inventory_item && ! $movementAtShiftClose && $totalCost > 0) {
            $journalLines[] = [
                'account_id' => $cogsAccount->id,
                'debit_amount' => $totalCost,
                'credit_amount' => 0,
                'product_id' => $product->id,
                'description' => 'Cost of '.$description,
            ];
            $journalLines[] = [
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
            'event_type' => $saleType.'_sale',
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'reference_no' => $sale->invoice_no,
            'description' => $description,
            'idempotency_key' => 'sale:'.$sale->id,
        ], $journalLines);

        if ($product->is_inventory_item && ! $movementAtShiftClose) {
            $this->inventory->record([
                'product_id' => $product->id,
                'shift_id' => $sale->shift_id,
                'journal_entry_id' => $journal->id,
                'business_date' => $sale->sale_date,
                'movement_type' => $saleType.'_sale',
                'quantity_out' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'source_line_id' => $item->id,
                'idempotency_key' => 'sale-item:'.$item->id,
            ]);
        }

        $sale->update([
            'journal_entry_id' => $journal->id,
            'status' => 'paid',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        SaleBatch::query()->create([
            'batch_code' => $batchCode,
            'sale_id' => $sale->id,
        ]);

        return $sale->fresh(['items.category', 'items.unit', 'journalEntry.lines.account']);
    }

    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->find($data['customer_id']);
        }

        return Customer::query()
            ->when(
                ! empty($data['mobile_number']),
                fn ($query) => $query->where('mobile', $data['mobile_number']),
                fn ($query) => $query->where('name', $data['customer'] ?? '')
            )
            ->first();
    }

    private function resolveVehicle(array $data, ?int $customerId): ?Vehicle
    {
        if (! empty($data['vehicle_id'])) {
            return Vehicle::query()->find($data['vehicle_id']);
        }

        if (empty($data['vehicle_no'])) {
            return null;
        }

        return Vehicle::query()
            ->where('vehicle_number', $data['vehicle_no'])
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->first();
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
