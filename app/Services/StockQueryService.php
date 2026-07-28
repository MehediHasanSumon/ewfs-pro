<?php

namespace App\Services;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Builder;

class StockQueryService
{
    public function filtered(array $filters): Builder
    {
        $query = Stock::query()->with([
            'product:id,category_id,unit_id,product_code,product_name',
            'product.unit:id,name',
            'product.category:id,name',
        ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', fn (Builder $product) => $product
                ->where(function (Builder $details) use ($search) {
                    $details->where('product_name', 'like', '%'.$search.'%')
                        ->orWhere('product_code', 'like', '%'.$search.'%');
                }));
        }

        if (
            ! empty($filters['category'])
            && $filters['category'] !== 'all'
        ) {
            $query->whereHas('product', fn (Builder $product) => $product
                ->where('category_id', $filters['category']));
        }

        match ($filters['status'] ?? null) {
            'in_stock' => $query->where('available_stock', '>', 0),
            'out_of_stock' => $query->where('available_stock', '<=', 0),
            'low_stock' => $query
                ->where('available_stock', '>', 0)
                ->where('available_stock', '<=', 10),
            default => null,
        };

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $sortBy = in_array(
            $filters['sort_by'] ?? null,
            [
                'id',
                'product_id',
                'opening_stock',
                'current_stock',
                'reserved_stock',
                'available_stock',
                'minimum_stock',
                'maximum_stock',
                'created_at',
                'updated_at',
            ],
            true
        ) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? null) === 'asc'
            ? 'asc'
            : 'desc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('id');
    }
}
