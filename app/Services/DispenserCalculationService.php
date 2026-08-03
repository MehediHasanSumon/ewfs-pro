<?php

namespace App\Services;

use App\Helpers\ErpHelper;
use App\Models\CreditSaleItem;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispenserCalculationService
{
    private const BANK_PAYMENT_METHODS = [
        'bank',
        'mobile_bank',
        'cheque',
        'online',
    ];

    private array $shiftSalesCache = [];

    public function calculate(
        array $items,
        float $creditSales = 0,
        float $bankSales = 0
    ): array {
        $quantities = $this->normalizeQuantities($items);
        $products = $this->otherProducts();
        $this->assertProductsWereResolved($quantities->keys(), $products);

        $resolved = $products->map(
            fn (Product $product) => $this->resolveLegacyProduct(
                $product,
                (float) $quantities->get($product->id, 0)
            )
        );
        $totalSales = round((float) $resolved->sum('line_total'), 4);
        $creditSales = round(max(0, $creditSales), 4);
        $bankSales = round(max(0, $bankSales), 4);
        $cashSales = round($totalSales - $creditSales - $bankSales, 4);

        return [
            'products' => $resolved
                ->map(fn (array $line) => $this->legacyProductPayload(
                    $line,
                    $totalSales,
                    $creditSales,
                    $bankSales
                ))
                ->values(),
            'summary' => [
                'total_sales' => $totalSales,
                'credit_sales' => $creditSales,
                'bank_sales' => $bankSales,
                'cash_sales' => max(0, $cashSales),
                'is_balanced' => $cashSales >= 0,
                'validation_message' => $cashSales < 0
                    ? 'Other product credit and bank sales cannot exceed total other product sales.'
                    : null,
            ],
        ];
    }

    public function calculateForShift(
        string $date,
        int $shiftId,
        array $items = []
    ): array {
        $quantities = $this->normalizeQuantities($items);
        $sales = $this->salesForShift($date, $shiftId);
        $products = $this->otherProducts();
        $this->assertProductsWereResolved($quantities->keys(), $products);

        $resolved = $products->map(function (Product $product) use (
            $quantities,
            $sales
        ): array {
            $recorded = $sales->get(
                $product->id,
                $this->emptySalesRecord($product->id)
            );
            $actualQuantity = $quantities->has($product->id)
                ? (float) $quantities->get($product->id)
                : (float) $recorded['auto_fill_quantity'];

            return $this->resolveShiftProduct(
                $product,
                $actualQuantity,
                $recorded
            );
        });

        return [
            'products' => $resolved
                ->map(fn (array $line) => $this->shiftProductPayload($line))
                ->values(),
            'summary' => $this->summarizeSales($sales),
        ];
    }

    public function resolveForClosing(array $items): Collection
    {
        $quantities = $this->normalizeQuantities($items, positiveOnly: true);

        if ($quantities->isEmpty()) {
            return collect();
        }

        $products = $this->otherProducts(
            $quantities->keys()->map(fn ($id) => (int) $id)->all(),
            lockStocks: true
        );
        $this->assertProductsWereResolved($quantities->keys(), $products);
        $itemsByProduct = collect($items)->keyBy(
            fn (array $item) => (int) $item['product_id']
        );

        return $products
            ->map(function (Product $product) use (
                $quantities,
                $itemsByProduct
            ): array {
                return $this->resolveLegacyProduct(
                    $product,
                    (float) $quantities->get($product->id)
                ) + [
                    'employee_id' => $itemsByProduct->get(
                        $product->id
                    )['employee_id'],
                ];
            })
            ->values();
    }

    public function resolveForShiftClosing(
        string $date,
        int $shiftId,
        array $items
    ): Collection {
        $quantities = $this->normalizeQuantities($items);
        $sales = $this->salesForShift($date, $shiftId);
        $productIds = $quantities->keys()
            ->merge($sales->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $products = $this->otherProducts(
            $productIds->all(),
            lockStocks: true
        );
        $this->assertProductsWereResolved($quantities->keys(), $products);
        $itemsByProduct = collect($items)->keyBy(
            fn (array $item) => (int) $item['product_id']
        );

        return $products
            ->map(function (Product $product) use (
                $quantities,
                $sales,
                $itemsByProduct
            ): array {
                $recorded = $sales->get(
                    $product->id,
                    $this->emptySalesRecord($product->id)
                );
                $actualQuantity = $quantities->has($product->id)
                    ? (float) $quantities->get($product->id)
                    : (float) $recorded['auto_fill_quantity'];
                $item = $itemsByProduct->get($product->id, []);
                $submittedRecordedQuantity = $item['recorded_quantity'] ?? null;

                if (
                    $submittedRecordedQuantity !== null
                    && abs(
                        (float) $submittedRecordedQuantity
                        - (float) $recorded['recorded_quantity']
                    ) > 0.0000001
                ) {
                    throw ValidationException::withMessages([
                        'other_product_sales' => 'Other product sales changed after this page was loaded. Refresh the shift data and review the quantities before closing.',
                    ]);
                }

                if ($actualQuantity > 0 && empty($item['employee_id'])) {
                    throw ValidationException::withMessages([
                        'other_product_sales' => "Sold By is required for {$product->product_name}.",
                    ]);
                }

                return $this->resolveShiftProduct(
                    $product,
                    $actualQuantity,
                    $recorded
                ) + [
                    'employee_id' => $item['employee_id'] ?? null,
                ];
            })
            ->filter(fn (array $line) => $line['quantity'] > 0
                || $line['recorded_quantity'] > 0
                || $line['line_total'] > 0
                || abs($line['quantity_variance']) > 0.0000001)
            ->values();
    }

    public function otherProductSalesSummary(
        string $date,
        int $shiftId
    ): array {
        return $this->summarizeSales($this->salesForShift($date, $shiftId));
    }

    private function salesForShift(string $date, int $shiftId): Collection
    {
        $cacheKey = $date.'|'.$shiftId;

        if (array_key_exists($cacheKey, $this->shiftSalesCache)) {
            return $this->shiftSalesCache[$cacheKey];
        }

        $excludedCodes = ErpHelper::dispenserProductCategoryCodes();
        $bankMethods = implode(
            ', ',
            array_map(
                fn (string $method): string => DB::getPdo()->quote($method),
                self::BANK_PAYMENT_METHODS
            )
        );
        $legacyPaymentMethods = DB::table('journal_lines')
            ->select('journal_entry_id')
            ->selectRaw('MAX(payment_method) AS payment_method')
            ->where('debit_amount', '>', 0)
            ->whereNotNull('payment_method')
            ->groupBy('journal_entry_id');
        $effectivePaymentMethod = "COALESCE(sale_payment_details.payment_method, sale_payment_lines.payment_method, 'cash')";
        $regularSales = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('categories', 'categories.id', '=', 'sale_items.category_id')
            ->join(
                'journal_entries',
                'journal_entries.id',
                '=',
                'sales.journal_entry_id'
            )
            ->leftJoin(
                'sale_payment_details',
                'sale_payment_details.sale_id',
                '=',
                'sales.id'
            )
            ->leftJoinSub(
                $legacyPaymentMethods,
                'sale_payment_lines',
                fn ($join) => $join->on(
                    'sale_payment_lines.journal_entry_id',
                    '=',
                    'sales.journal_entry_id'
                )
            )
            ->whereDate('sales.sale_date', $date)
            ->where('sales.shift_id', $shiftId)
            ->where('journal_entries.status', 'posted')
            ->whereNotIn('categories.code', $excludedCodes)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id')
            ->selectRaw(
                "SUM(CASE WHEN sales.sale_type = 'regular' THEN sale_items.quantity ELSE 0 END) AS regular_quantity"
            )
            ->selectRaw(
                "SUM(CASE WHEN sales.sale_type = 'white' THEN sale_items.quantity ELSE 0 END) AS white_quantity"
            )
            ->selectRaw(
                "SUM(CASE WHEN sales.sale_type = 'regular' AND {$effectivePaymentMethod} IN ({$bankMethods}) THEN sale_items.line_total ELSE 0 END) AS bank_sales"
            )
            ->selectRaw(
                "SUM(CASE WHEN sales.sale_type = 'white' OR {$effectivePaymentMethod} NOT IN ({$bankMethods}) THEN sale_items.line_total ELSE 0 END) AS cash_sales"
            )
            ->get()
            ->keyBy(fn ($row) => (int) $row->product_id);

        $creditSales = CreditSaleItem::query()
            ->join(
                'credit_sale_customers',
                'credit_sale_customers.id',
                '=',
                'credit_sale_items.credit_sale_customer_id'
            )
            ->join(
                'credit_sales',
                'credit_sales.id',
                '=',
                'credit_sale_customers.credit_sale_id'
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'credit_sale_items.category_id'
            )
            ->join(
                'journal_entries',
                'journal_entries.id',
                '=',
                'credit_sale_customers.journal_entry_id'
            )
            ->whereDate('credit_sales.sale_date', $date)
            ->where('credit_sales.shift_id', $shiftId)
            ->where('journal_entries.status', 'posted')
            ->whereNotIn('categories.code', $excludedCodes)
            ->groupBy('credit_sale_items.product_id')
            ->selectRaw('credit_sale_items.product_id')
            ->selectRaw(
                'SUM(credit_sale_items.quantity) AS credit_quantity'
            )
            ->selectRaw(
                'SUM(credit_sale_items.line_total) AS credit_sales'
            )
            ->get()
            ->keyBy(fn ($row) => (int) $row->product_id);

        $productIds = $regularSales->keys()
            ->merge($creditSales->keys())
            ->unique();
        $sales = $productIds->mapWithKeys(function (int $productId) use (
            $regularSales,
            $creditSales
        ): array {
            $regular = $regularSales->get($productId);
            $credit = $creditSales->get($productId);
            $regularQuantity = (float) ($regular?->regular_quantity ?? 0);
            $whiteQuantity = (float) ($regular?->white_quantity ?? 0);
            $creditQuantity = (float) ($credit?->credit_quantity ?? 0);
            $creditAmount = round(
                (float) ($credit?->credit_sales ?? 0),
                4
            );
            $bankAmount = round(
                (float) ($regular?->bank_sales ?? 0),
                4
            );
            $cashAmount = round(
                (float) ($regular?->cash_sales ?? 0),
                4
            );

            return [$productId => [
                'product_id' => $productId,
                'regular_quantity' => $regularQuantity,
                'white_quantity' => $whiteQuantity,
                'credit_quantity' => $creditQuantity,
                'auto_fill_quantity' => round(
                    $regularQuantity + $creditQuantity,
                    6
                ),
                'recorded_quantity' => round(
                    $regularQuantity + $whiteQuantity + $creditQuantity,
                    6
                ),
                'credit_sales' => $creditAmount,
                'bank_sales' => $bankAmount,
                'cash_sales' => $cashAmount,
                'total_sales' => round(
                    $creditAmount + $bankAmount + $cashAmount,
                    4
                ),
            ]];
        });

        return $this->shiftSalesCache[$cacheKey] = $sales;
    }

    private function otherProducts(
        ?array $productIds = null,
        bool $lockStocks = false
    ): EloquentCollection {
        $products = Product::query()
            ->with([
                'category:id,code',
                'unit:id,name,quantity_scale',
                'activeRate',
            ])
            ->active()
            ->otherForDispenser()
            ->when($productIds !== null, fn ($query) => $query
                ->whereIn('id', $productIds))
            ->orderBy('product_name')
            ->orderBy('id')
            ->get([
                'id',
                'category_id',
                'unit_id',
                'product_code',
                'product_name',
                'is_inventory_item',
            ]);

        $stocks = Stock::query()
            ->whereIn('product_id', $products->modelKeys())
            ->when($lockStocks, fn ($query) => $query->lockForUpdate())
            ->get([
                'id',
                'product_id',
                'current_stock',
                'available_stock',
            ])
            ->keyBy('product_id');

        return $products->each(
            fn (Product $product) => $product->setRelation(
                'stock',
                $stocks->get($product->id)
            )
        );
    }

    private function normalizeQuantities(
        array $items,
        bool $positiveOnly = false
    ): Collection {
        $quantities = collect();

        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = $item['quantity'] ?? 0;

            if ($productId <= 0 || ! is_numeric($quantity)) {
                throw ValidationException::withMessages([
                    "other_product_sales.{$index}" => 'Invalid other product sale data.',
                ]);
            }

            $quantity = (float) $quantity;

            if ($quantity < 0 || ($positiveOnly && $quantity <= 0)) {
                throw ValidationException::withMessages([
                    "other_product_sales.{$index}.quantity" => $positiveOnly
                        ? 'Sell quantity must be greater than zero.'
                        : 'Sell quantity cannot be negative.',
                ]);
            }

            if ($quantities->has($productId)) {
                throw ValidationException::withMessages([
                    "other_product_sales.{$index}.product_id" => 'The same product cannot be submitted more than once.',
                ]);
            }

            $quantities->put($productId, $quantity);
        }

        return $quantities;
    }

    private function assertProductsWereResolved(
        Collection $requestedIds,
        EloquentCollection $products
    ): void {
        if ($requestedIds->isEmpty()) {
            return;
        }

        $resolvedIds = $products->modelKeys();
        $missing = $requestedIds
            ->map(fn ($id) => (int) $id)
            ->diff($resolvedIds);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'other_product_sales' => 'Only active products outside the configured Oil and Gas categories can be recorded as other product sales.',
            ]);
        }
    }

    private function resolveLegacyProduct(
        Product $product,
        float $quantity
    ): array {
        $roundedQuantity = $this->validateQuantity($product, $quantity);
        $currentStock = (float) ($product->stock?->current_stock ?? 0);

        if ($product->is_inventory_item && $roundedQuantity > $currentStock) {
            throw ValidationException::withMessages([
                'other_product_sales' => "Sell quantity for {$product->product_name} cannot exceed current stock.",
            ]);
        }

        $unitPrice = round(
            (float) ($product->activeRate?->sales_price ?? 0),
            6
        );
        $unitCost = round(
            (float) ($product->activeRate?->purchase_price ?? 0),
            6
        );

        return [
            'product' => $product,
            'quantity' => $roundedQuantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'line_total' => round($roundedQuantity * $unitPrice, 4),
            'total_cost' => round($roundedQuantity * $unitCost, 4),
            'current_stock' => $currentStock,
            'remaining_stock' => $product->is_inventory_item
                ? round($currentStock - $roundedQuantity, 6)
                : $currentStock,
        ];
    }

    private function resolveShiftProduct(
        Product $product,
        float $actualQuantity,
        array $recorded
    ): array {
        $actualQuantity = $this->validateQuantity(
            $product,
            $actualQuantity
        );
        $recordedQuantity = $this->validateQuantity(
            $product,
            (float) $recorded['recorded_quantity'],
            'Recorded quantity'
        );
        $quantityVariance = round(
            $actualQuantity - $recordedQuantity,
            6
        );
        $currentStock = (float) ($product->stock?->current_stock ?? 0);

        if (
            $product->is_inventory_item
            && $quantityVariance > $currentStock
        ) {
            throw ValidationException::withMessages([
                'other_product_sales' => "Additional sell quantity for {$product->product_name} cannot exceed current stock.",
            ]);
        }

        $unitPrice = round(
            (float) ($product->activeRate?->sales_price ?? 0),
            6
        );
        $unitCost = round(
            (float) ($product->activeRate?->purchase_price ?? 0),
            6
        );

        return [
            'product' => $product,
            'quantity' => $actualQuantity,
            'recorded_quantity' => $recordedQuantity,
            'quantity_variance' => $quantityVariance,
            'regular_quantity' => (float) $recorded['regular_quantity'],
            'white_quantity' => (float) $recorded['white_quantity'],
            'credit_quantity' => (float) $recorded['credit_quantity'],
            'auto_fill_quantity' => (float) $recorded['auto_fill_quantity'],
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'line_total' => round((float) $recorded['total_sales'], 4),
            'credit_sales' => round((float) $recorded['credit_sales'], 4),
            'bank_sales' => round((float) $recorded['bank_sales'], 4),
            'cash_sales' => round((float) $recorded['cash_sales'], 4),
            'total_cost' => round(abs($quantityVariance) * $unitCost, 4),
            'current_stock' => $currentStock,
            'remaining_stock' => $product->is_inventory_item
                ? round($currentStock - $quantityVariance, 6)
                : $currentStock,
        ];
    }

    private function validateQuantity(
        Product $product,
        float $quantity,
        string $label = 'Sell quantity'
    ): float {
        $scale = max(
            0,
            min((int) ($product->unit?->quantity_scale ?? 0), 6)
        );
        $roundedQuantity = round($quantity, $scale);

        if (abs($quantity - $roundedQuantity) > 0.0000001) {
            throw ValidationException::withMessages([
                'other_product_sales' => "{$label} for {$product->product_name} supports at most {$scale} decimal places.",
            ]);
        }

        return $roundedQuantity;
    }

    private function legacyProductPayload(
        array $line,
        float $totalSales,
        float $creditSales,
        float $bankSales
    ): array {
        $proportion = $totalSales > 0
            ? $line['line_total'] / $totalSales
            : 0;
        $productCredit = round($creditSales * $proportion, 4);
        $productBank = round($bankSales * $proportion, 4);

        return $this->baseProductPayload($line) + [
            'sell_quantity' => $line['quantity'],
            'recorded_quantity' => 0,
            'quantity_variance' => $line['quantity'],
            'remaining_stock' => $line['remaining_stock'],
            'total_sales' => $line['line_total'],
            'credit_sales' => $productCredit,
            'bank_sales' => $productBank,
            'cash_sales' => max(
                0,
                round(
                    $line['line_total'] - $productCredit - $productBank,
                    4
                )
            ),
        ];
    }

    private function shiftProductPayload(array $line): array
    {
        return $this->baseProductPayload($line) + [
            'sell_quantity' => $line['quantity'],
            'recorded_quantity' => $line['recorded_quantity'],
            'quantity_variance' => $line['quantity_variance'],
            'regular_quantity' => $line['regular_quantity'],
            'white_quantity' => $line['white_quantity'],
            'credit_quantity' => $line['credit_quantity'],
            'auto_fill_quantity' => $line['auto_fill_quantity'],
            'remaining_stock' => $line['remaining_stock'],
            'total_sales' => $line['line_total'],
            'credit_sales' => $line['credit_sales'],
            'bank_sales' => $line['bank_sales'],
            'cash_sales' => $line['cash_sales'],
        ];
    }

    private function baseProductPayload(array $line): array
    {
        /** @var Product $product */
        $product = $line['product'];

        return [
            'id' => $product->id,
            'product_name' => $product->product_name,
            'product_code' => $product->product_code,
            'sales_price' => $line['unit_price'],
            'unit' => [
                'name' => $product->unit?->name,
                'quantity_scale' => (int) (
                    $product->unit?->quantity_scale ?? 0
                ),
            ],
            'stock' => [
                'current_stock' => $line['current_stock'],
            ],
        ];
    }

    private function summarizeSales(Collection $sales): array
    {
        $totalSales = round((float) $sales->sum('total_sales'), 4);
        $creditSales = round((float) $sales->sum('credit_sales'), 4);
        $bankSales = round((float) $sales->sum('bank_sales'), 4);
        $cashSales = round((float) $sales->sum('cash_sales'), 4);
        $difference = round(
            $totalSales - $creditSales - $bankSales - $cashSales,
            4
        );

        return [
            'total_sales' => $totalSales,
            'credit_sales' => $creditSales,
            'bank_sales' => $bankSales,
            'cash_sales' => $cashSales,
            'is_balanced' => abs($difference) < 0.0001,
            'validation_message' => abs($difference) >= 0.0001
                ? 'Posted other product sales are not balanced by payment method.'
                : null,
        ];
    }

    private function emptySalesRecord(int $productId): array
    {
        return [
            'product_id' => $productId,
            'regular_quantity' => 0,
            'white_quantity' => 0,
            'credit_quantity' => 0,
            'auto_fill_quantity' => 0,
            'recorded_quantity' => 0,
            'credit_sales' => 0,
            'bank_sales' => 0,
            'cash_sales' => 0,
            'total_sales' => 0,
        ];
    }
}
