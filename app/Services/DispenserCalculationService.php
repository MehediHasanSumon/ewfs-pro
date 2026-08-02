<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DispenserCalculationService
{
    public function calculate(
        array $items,
        float $creditSales = 0,
        float $bankSales = 0
    ): array {
        $quantities = $this->normalizeQuantities($items);
        $products = $this->otherProducts();
        $this->assertProductsWereResolved($quantities->keys(), $products);

        $resolved = $products->map(
            fn (Product $product) => $this->resolveProduct(
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
                ->map(fn (array $line) => $this->productPayload(
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
                return $this->resolveProduct(
                    $product,
                    (float) $quantities->get($product->id)
                ) + [
                    'employee_id' => $itemsByProduct->get($product->id)['employee_id'],
                ];
            })
            ->values();
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

    private function resolveProduct(Product $product, float $quantity): array
    {
        $scale = max(0, min((int) ($product->unit?->quantity_scale ?? 0), 6));
        $roundedQuantity = round($quantity, $scale);

        if (abs($quantity - $roundedQuantity) > 0.0000001) {
            throw ValidationException::withMessages([
                'other_product_sales' => "Sell quantity for {$product->product_name} supports at most {$scale} decimal places.",
            ]);
        }

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

    private function productPayload(
        array $line,
        float $totalSales,
        float $creditSales,
        float $bankSales
    ): array {
        /** @var Product $product */
        $product = $line['product'];
        $proportion = $totalSales > 0
            ? $line['line_total'] / $totalSales
            : 0;
        $productCredit = round($creditSales * $proportion, 4);
        $productBank = round($bankSales * $proportion, 4);

        return [
            'id' => $product->id,
            'product_name' => $product->product_name,
            'product_code' => $product->product_code,
            'sales_price' => $line['unit_price'],
            'unit' => [
                'name' => $product->unit?->name,
                'quantity_scale' => (int) ($product->unit?->quantity_scale ?? 0),
            ],
            'stock' => [
                'current_stock' => $line['current_stock'],
            ],
            'sell_quantity' => $line['quantity'],
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
}
