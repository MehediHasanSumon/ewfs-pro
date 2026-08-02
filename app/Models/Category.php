<?php

namespace App\Models;

use App\Helpers\ErpHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    protected $fillable = [
        'name',
        'code',
        'inventory_class',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (! ErpHelper::isReservedCategoryCode($category->code)) {
                throw ValidationException::withMessages([
                    'code' => 'The selected category code is not a reserved ERP category code.',
                ]);
            }
        });

        static::updating(function (Category $category): void {
            if ($category->isDirty('code')) {
                $category->code = $category->getOriginal('code');
            }
        });

        static::deleting(function (Category $category): void {
            if (ErpHelper::isReservedCategoryCode($category->code)) {
                throw ValidationException::withMessages([
                    'category' => 'Reserved ERP categories cannot be deleted.',
                ]);
            }
        });
    }

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

    public function creditSaleItems(): HasMany
    {
        return $this->hasMany(CreditSaleItem::class);
    }
}
