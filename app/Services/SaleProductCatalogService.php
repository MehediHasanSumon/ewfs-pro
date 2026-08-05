<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SaleProductCatalogService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forSelection(): Collection
    {
        return $this->query()
            ->with([
                'category:id,name',
                'unit:id,name',
                'stock:id,product_id,current_stock,available_stock',
                'activeRate',
            ])
            ->orderBy('product_name')
            ->orderBy('id')
            ->get([
                'id',
                'category_id',
                'unit_id',
                'product_name',
                'product_code',
                'is_inventory_item',
            ])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'unit' => $product->unit ? [
                    'id' => $product->unit->id,
                    'name' => $product->unit->name,
                ] : null,
                'is_inventory_item' => (bool) $product->is_inventory_item,
                'sales_price' => $product->activeRate?->sales_price !== null
                    ? (float) $product->activeRate->sales_price
                    : null,
                'stock' => $product->stock ? [
                    'current_stock' => (float) $product->stock->current_stock,
                    'available_stock' => (float) $product->stock->available_stock,
                ] : null,
            ]);
    }

    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, Product>
     */
    public function resolve(array $productIds): Collection
    {
        return $this->query()
            ->with(['category', 'unit', 'activeRate'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
    }

    public function query(): Builder
    {
        $excludedCategoryCodes = $this->excludedCategoryCodes();

        return Product::query()
            ->active()
            ->whereHas(
                'category',
                fn (Builder $category) => $category->active()
            )
            ->when(
                $excludedCategoryCodes !== [],
                fn (Builder $query) => $query->whereDoesntHave(
                    'category',
                    fn (Builder $category) => $category->whereIn(
                        'code',
                        $excludedCategoryCodes
                    )
                )
            );
    }

    /**
     * @return array<int, string>
     */
    private function excludedCategoryCodes(): array
    {
        $codes = config('erp.sales.excluded_category_codes', []);

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($code): string => trim((string) $code),
            $codes
        ))));
    }
}
