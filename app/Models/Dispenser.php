<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispenser extends Model
{
    protected $fillable = [
        'dispenser_name',
        'product_id',
        'dispenser_item',
        'status',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(DispenserReading::class);
    }
}
