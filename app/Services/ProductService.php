<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function delete(Product $product): void
    {
        $this->deleteMany([$product->getKey()]);
    }

    public function deleteMany(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            /** @var Collection<int, Product> $products */
            $products = Product::query()->whereKey($ids)->lockForUpdate()->get();

            foreach ($products as $product) {
                if ($this->hasOperationalHistory($product)) {
                    throw ValidationException::withMessages([
                        'ids' => "Product {$product->product_name} has operational history and cannot be deleted.",
                    ]);
                }
            }

            foreach ($products as $product) {
                $product->vehicles()->detach();
                $product->rates()->delete();
                $product->delete();
            }

            return $products->count();
        });
    }

    private function hasOperationalHistory(Product $product): bool
    {
        return $product->saleItems()->exists()
            || $product->purchaseItems()->exists()
            || $product->creditSaleItems()->exists()
            || $product->inventoryMovements()->exists()
            || $product->dispenserReadings()->exists()
            || $product->shiftClosingProductItems()->exists()
            || $product->dispensers()->exists()
            || $product->stock()
                ->where(function ($query) {
                    $query->where('opening_stock', '>', 0)
                        ->orWhere('current_stock', '>', 0)
                        ->orWhere('reserved_stock', '>', 0);
                })
                ->exists();
    }
}
