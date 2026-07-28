<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'customer_id',
        'vehicle_type',
        'vehicle_name',
        'vehicle_number',
        'reg_date',
        'status'
    ];

    protected $casts = [
        'reg_date' => 'date',
        'status' => 'boolean'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vehicle_products');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditSaleItems(): HasMany
    {
        return $this->hasMany(CreditSaleItem::class);
    }
}
