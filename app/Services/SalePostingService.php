<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Vehicle;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalePostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers,
        private readonly SalesCustomerService $customers,
        private readonly PaymentAccountService $paymentAccounts
    ) {}

    public function create(array $data, string $saleType = 'regular'): Sale
    {
        return $this->withCustomerMobileLock(
            $data,
            fn () => DB::transaction(
                fn () => $this->postRegularSale(null, $data, $saleType)
            )
        );
    }

    public function replace(Sale $sale, array $data): Sale
    {
        return $this->withCustomerMobileLock(
            $data,
            fn () => DB::transaction(function () use ($sale, $data) {
                $lockedSale = Sale::query()
                    ->whereKey($sale->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSale->loadMissing('journalEntry');

                if ($lockedSale->journalEntry?->status !== 'posted') {
                    throw ValidationException::withMessages([
                        'sale' => 'Only an active posted sale can be edited.',
                    ]);
                }

                $this->accounting->reverse(
                    $lockedSale->journalEntry,
                    'Sale reposted from the edit workflow.'
                );

                $this->inventory->reverseSource(
                    Sale::class,
                    $lockedSale->id,
                    'Sale reposted from the edit workflow.'
                );

                $lockedSale->update([
                    'journal_entry_id' => null,
                    'status' => 'draft',
                    'posted_by' => null,
                    'posted_at' => null,
                ]);
                $lockedSale->items()->delete();

                return $this->postRegularSale(
                    $lockedSale,
                    $data,
                    $lockedSale->sale_type
                );
            })
        );
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
            $this->inventory->assertAvailable(
                $resolvedProducts
                    ->filter(fn (array $resolved) => $resolved['product']
                        ->is_inventory_item)
                    ->groupBy(fn (array $resolved) => $resolved['product']->id)
                    ->map(fn ($lines) => round(
                        (float) $lines->sum('quantity'),
                        6
                    ))
                    ->all()
            );
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

                $movementItems[] = compact(
                    'item',
                    'product',
                    'unitCost',
                    'totalCost'
                ) + [
                    'quantity' => $resolved['quantity'],
                ];

                if ($product->is_inventory_item && $totalCost > 0) {
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

            $this->inventory->recordMany(
                collect($movementItems)
                    ->filter(fn (array $movementItem) => $movementItem['product']
                        ->is_inventory_item)
                    ->map(fn (array $movementItem) => [
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
                        'remarks' => $sale->remarks,
                    ])
                    ->values()
                    ->all()
            );

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

    private function postRegularSale(
        ?Sale $sale,
        array $data,
        string $saleType
    ): Sale {
        $resolvedItems = $this->resolveItems($data['items']);
        $this->inventory->assertAvailable(
            collect($resolvedItems)
                ->filter(fn (array $resolved) => $resolved['product']
                    ->is_inventory_item)
                ->groupBy(fn (array $resolved) => $resolved['product']->id)
                ->map(fn ($lines) => round(
                    (float) $lines->sum('quantity'),
                    6
                ))
                ->all()
        );
        $customer = $this->customers->resolve($data);
        $vehicle = $this->resolveVehicle($data, $customer);
        $currencyScale = $this->currencyScale();
        $subtotal = round(
            (float) collect($resolvedItems)->sum('gross_amount'),
            $currencyScale
        );
        $discountTotal = round(
            (float) collect($resolvedItems)->sum('discount'),
            $currencyScale
        );
        $grandTotal = round(
            (float) collect($resolvedItems)->sum('line_total'),
            $currencyScale
        );
        if ($grandTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => 'The sale total must be greater than zero.',
            ]);
        }

        $paymentAccount = $this->paymentAccounts->resolve(
            (int) $data['to_account_id'],
            $data['payment_type'],
            'to_account_id'
        );
        $businessDate = $data['sale_date'] ?? now()->toDateString();
        $mobile = $this->customers->normalizeMobile($data['customer_mobile']);
        $vehicleNumber = $vehicle?->vehicle_number
            ?? ($data['vehicle_no'] ?: null);
        $attributes = [
            'shift_id' => $data['shift_id'],
            'customer_id' => $customer?->id,
            'vehicle_id' => $vehicle?->id,
            'sale_type' => $saleType,
            'sale_date' => $businessDate,
            'sale_time' => now()->format('H:i:s'),
            'memo_no' => $data['memo_no'] ?? null,
            'customer_name_snapshot' => $customer?->name
                ?? $data['customer_name'],
            'customer_mobile_snapshot' => $customer?->mobile ?? $mobile,
            'vehicle_number_snapshot' => $vehicleNumber,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'status' => 'draft',
            'remarks' => $data['remarks'] ?? null,
        ];

        if ($sale) {
            $sale->update($attributes);
        } else {
            $sale = Sale::query()->create($attributes + [
                'invoice_no' => $this->numbers->next(
                    'invoice',
                    'IN',
                    $businessDate,
                    4
                ),
                'created_by' => auth()->id(),
            ]);
        }

        $items = $sale->items()->createMany(
            collect($resolvedItems)
                ->values()
                ->map(fn (array $resolved, int $index) => [
                    'line_no' => $index + 1,
                    'product_id' => $resolved['product']->id,
                    'category_id' => $resolved['product']->category_id,
                    'unit_id' => $resolved['product']->unit_id,
                    'product_code_snapshot' => $resolved['product']->product_code,
                    'product_name_snapshot' => $resolved['product']->product_name,
                    'category_name_snapshot' => $resolved['product']->category->name,
                    'unit_name_snapshot' => $resolved['product']->unit->name,
                    'quantity' => $resolved['quantity'],
                    'unit_price' => $resolved['unit_price'],
                    'unit_cost' => $resolved['unit_cost'],
                    'discount_amount' => $resolved['discount'],
                    'tax_amount' => 0,
                    'line_total' => $resolved['line_total'],
                    'remarks' => $resolved['remarks'],
                ])
                ->all()
        );

        $description = ucfirst($saleType).' sale '.$sale->invoice_no;
        $revenueAccount = $this->systemAccounts->salesRevenue();
        $inventoryAccount = $this->systemAccounts->inventoryAsset();
        $cogsAccount = $this->systemAccounts->costOfGoodsSold();
        $paymentMethod = $this->normalizePaymentMethod($data);
        $journalLines = [[
            'account_id' => $paymentAccount->id,
            'debit_amount' => $grandTotal,
            'credit_amount' => 0,
            'payment_method' => $paymentMethod,
            'description' => $description,
        ]];

        foreach ($resolvedItems as $resolved) {
            $product = $resolved['product'];
            $journalLines[] = [
                'account_id' => $revenueAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $resolved['line_total'],
                'product_id' => $product->id,
                'description' => $description,
            ];

            if (
                $product->is_inventory_item
                && $resolved['total_cost'] > 0
            ) {
                $journalLines[] = [
                    'account_id' => $cogsAccount->id,
                    'debit_amount' => $resolved['total_cost'],
                    'credit_amount' => 0,
                    'product_id' => $product->id,
                    'description' => 'Cost of '.$description,
                ];
                $journalLines[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $resolved['total_cost'],
                    'product_id' => $product->id,
                    'description' => 'Inventory issued for '.$description,
                ];
            }
        }

        $journal = $this->accounting->post([
            'shift_id' => $sale->shift_id,
            'business_date' => $sale->sale_date,
            'event_type' => $saleType.'_sale',
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'reference_no' => $sale->invoice_no,
            'description' => $description,
            'idempotency_key' => 'sale:'.$sale->id.':revision:'.Str::uuid(),
        ], $journalLines);

        $this->inventory->recordMany(
            collect($resolvedItems)
                ->values()
                ->filter(fn (array $resolved) => $resolved['product']
                    ->is_inventory_item)
                ->map(function (array $resolved, int $index) use (
                    $items,
                    $journal,
                    $sale,
                    $saleType
                ) {
                    $item = $items[$index];

                    return [
                        'product_id' => $resolved['product']->id,
                        'shift_id' => $sale->shift_id,
                        'journal_entry_id' => $journal->id,
                        'business_date' => $sale->sale_date,
                        'movement_type' => $saleType.'_sale',
                        'quantity_out' => $resolved['quantity'],
                        'unit_cost' => $resolved['unit_cost'],
                        'total_cost' => $resolved['total_cost'],
                        'source_type' => Sale::class,
                        'source_id' => $sale->id,
                        'source_line_id' => $item->id,
                        'idempotency_key' => 'sale-item:'.$item->id
                            .':revision:'.Str::uuid(),
                        'remarks' => $sale->remarks,
                    ];
                })
                ->values()
                ->all()
        );

        $sale->paymentDetail()->updateOrCreate([], [
            'account_id' => $paymentAccount->id,
            'payment_method' => $paymentMethod,
            'bank_type' => $data['bank_type'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'branch_name' => $data['branch_name'] ?? null,
            'account_number' => $data['account_no'] ?? null,
            'cheque_number' => $data['cheque_no'] ?? null,
            'cheque_date' => $data['cheque_date'] ?? null,
            'mobile_bank_name' => $data['mobile_bank'] ?? null,
            'mobile_number' => $data['payment_mobile_number'] ?? null,
        ]);

        $sale->update([
            'journal_entry_id' => $journal->id,
            'status' => 'paid',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $sale->fresh([
            'items.product',
            'items.category',
            'items.unit',
            'journalEntry.lines.account',
            'paymentDetail.account',
        ]);
    }

    private function resolveItems(array $items): array
    {
        $currencyScale = $this->currencyScale();
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $products = Product::query()
            ->with(['category', 'unit', 'activeRate'])
            ->where('status', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected products are unavailable.',
            ]);
        }

        return collect($items)
            ->values()
            ->map(function (array $line, int $index) use (
                $currencyScale,
                $products
            ) {
                $product = $products->get((int) $line['product_id']);
                $salesPrice = $product?->activeRate?->sales_price;

                if ($salesPrice === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'The selected product has no active sales price.',
                    ]);
                }

                $quantity = round((float) $line['quantity'], 6);
                $unitPrice = round((float) $salesPrice, 6);
                $grossAmount = round(
                    $quantity * $unitPrice,
                    $currencyScale
                );
                $discount = round(
                    (float) ($line['discount'] ?? 0),
                    $currencyScale
                );

                if ($discount > $grossAmount) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount" => 'Discount cannot exceed the product amount.',
                    ]);
                }

                $unitCost = round(
                    (float) ($product->activeRate?->purchase_price ?? 0),
                    6
                );
                $lineTotal = round(
                    $grossAmount - $discount,
                    $currencyScale
                );

                if ($lineTotal <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount" => 'The product total must be greater than zero.',
                    ]);
                }

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'gross_amount' => $grossAmount,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                    'total_cost' => round($quantity * $unitCost, 4),
                    'remarks' => $line['remarks'] ?? null,
                ];
            })
            ->all();
    }

    private function resolveVehicle(
        array $data,
        ?Customer $customer
    ): ?Vehicle {
        if (! empty($data['vehicle_id'])) {
            return Vehicle::query()
                ->where('status', true)
                ->findOrFail($data['vehicle_id']);
        }

        if (empty($data['vehicle_no'])) {
            return null;
        }

        $vehicle = Vehicle::query()
            ->where('status', true)
            ->where('vehicle_number', $data['vehicle_no'])
            ->orderBy('id')
            ->first();

        if ($vehicle || ! $customer) {
            return $vehicle;
        }

        return Vehicle::query()->create([
            'customer_id' => $customer->id,
            'vehicle_name' => $data['vehicle_no'],
            'vehicle_number' => $data['vehicle_no'],
            'status' => true,
        ]);
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

    private function currencyScale(): int
    {
        return min(4, max(0, (int) config('erp.sales.currency_scale', 2)));
    }

    private function withCustomerMobileLock(array $data, callable $callback): Sale
    {
        $mobile = $this->customers->normalizeMobile(
            (string) ($data['customer_mobile'] ?? '')
        );

        try {
            return Cache::lock(
                'sales-customer-mobile:'.sha1($mobile),
                15
            )->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'customer_mobile' => 'This mobile number is being processed. Please try again.',
            ]);
        }
    }
}
