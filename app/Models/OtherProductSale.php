<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherProductSale extends Model
{
    protected $fillable = [
        'sale_date',
        'shift_id',
        'employee_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_amount',
        'remarks',
        'status'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => 'boolean'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
