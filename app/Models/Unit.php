<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'value',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function dailyOtherProductSales()
    {
        return $this->hasMany(DailyOtherProductSale::class);
    }

    public function whiteSaleProducts()
    {
        return $this->hasMany(WhiteSaleProduct::class);
    }
}
