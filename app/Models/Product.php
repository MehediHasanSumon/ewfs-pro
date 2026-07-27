<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'unit_id',
        'product_code',
        'product_name',
        'product_slug',
        'country_Of_origin',
        'remarks',
        'status'
    ];

    protected $casts = [
        'status' => 'integer'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function rates()
    {
        return $this->hasMany(ProductRate::class);
    }

    public function activeRate()
    {
        return $this->hasOne(ProductRate::class)->where('status', true)->latest('effective_date');
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
