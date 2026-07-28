<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $appends = [
        'sales_price',
        'amount',
    ];

    protected $fillable = [
        'sale_id',
        'line_no',
        'product_id',
        'category_id',
        'unit_id',
        'product_code_snapshot',
        'product_name_snapshot',
        'category_name_snapshot',
        'unit_name_snapshot',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'tax_amount',
        'line_total',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function getSalesPriceAttribute(): float
    {
        return (float) $this->unit_price;
    }

    public function getAmountAttribute(): float
    {
        return (float) $this->line_total;
    }
}
