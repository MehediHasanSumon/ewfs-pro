<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRate extends Model
{
    protected $fillable = [
        'product_id',
        'purchase_price',
        'sales_price',
        'effective_date',
        'status'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:6',
        'sales_price' => 'decimal:6',
        'effective_date' => 'date',
        'status' => 'boolean'
    ];

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', true)->whereDate('effective_date', '<=', $date);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
