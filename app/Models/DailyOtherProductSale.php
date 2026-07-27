<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyOtherProductSale extends Model
{
    protected $fillable = [
        'date',
        'shift_id',
        'product_id',
        'unit_id',
        'item_rate',
        'sell_quantity',
        'employee_id',
        'total_sales'
    ];

    protected $casts = [
        'date' => 'date',
        'item_rate' => 'decimal:2',
        'sell_quantity' => 'decimal:2',
        'total_sales' => 'decimal:2'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
