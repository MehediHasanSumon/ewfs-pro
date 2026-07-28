<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dispenser extends Model
{
    protected $fillable = [
        'code',
        'dispenser_name',
        'product_id',
        'dispenser_item',
        'opening_reading',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_reading' => 'decimal:6',
            'status' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(DispenserReading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(DispenserReading::class)->latestOfMany();
    }
}
