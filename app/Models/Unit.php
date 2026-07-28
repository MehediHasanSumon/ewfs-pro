<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'value',
        'quantity_scale',
        'status',
    ];

    protected $casts = [
        'quantity_scale' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creditSaleItems(): HasMany
    {
        return $this->hasMany(CreditSaleItem::class);
    }

    public function shiftClosingProductItems(): HasMany
    {
        return $this->hasMany(ShiftClosingProductItem::class);
    }
}
