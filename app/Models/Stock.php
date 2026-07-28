<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $fillable = [
        'product_id',
        'opening_stock',
        'current_stock',
        'reserved_stock',
        'available_stock',
        'minimum_stock',
        'maximum_stock',
        'last_movement_id',
        'version',
        'refreshed_at',
    ];

    protected $casts = [
        'opening_stock' => 'decimal:6',
        'current_stock' => 'decimal:6',
        'reserved_stock' => 'decimal:6',
        'available_stock' => 'decimal:6',
        'minimum_stock' => 'decimal:6',
        'maximum_stock' => 'decimal:6',
        'last_movement_id' => 'integer',
        'version' => 'integer',
        'refreshed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id');
    }

    public function lastMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'last_movement_id');
    }
}
