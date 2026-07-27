<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhiteSaleProduct extends Model
{
    protected $fillable = [
        'white_sale_id',
        'product_id',
        'category_id',
        'unit_id',
        'quantity',
        'sales_price',
        'amount'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'amount' => 'decimal:2'
    ];

    public function whiteSale()
    {
        return $this->belongsTo(WhiteSale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}