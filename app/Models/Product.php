<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'unit_id',
        'product_code',
        'product_name',
        'product_slug',
        'country_of_origin',
        'sku',
        'is_inventory_item',
        'remarks',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_inventory_item' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    public function activeRate(): HasOne
    {
        return $this->hasOne(ProductRate::class)
            ->ofMany(
                [
                    'effective_date' => 'max',
                    'id' => 'max',
                ],
                fn (Builder $query) => $query
                    ->where('status', true)
                    ->whereDate('effective_date', '<=', now()->toDateString())
            );
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_products')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function creditSaleItems(): HasMany
    {
        return $this->hasMany(CreditSaleItem::class);
    }

    public function dispensers(): HasMany
    {
        return $this->hasMany(Dispenser::class);
    }

    public function dispenserReadings(): HasMany
    {
        return $this->hasMany(DispenserReading::class);
    }

    public function shiftClosingProductItems(): HasMany
    {
        return $this->hasMany(ShiftClosingProductItem::class);
    }
}
