<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditSale extends Model
{
    protected $fillable = [
        'sale_date',
        'sale_time',
        'invoice_no',
        'memo_no',
        'shift_id',
        'transaction_id',
        'customer_id',
        'vehicle_id',
        'product_id',
        'category_code',
        'type',
        'purchase_price',
        'quantity',
        'amount',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'remarks',
        'status',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'purchase_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'status' => 'boolean',
        'type' => 'string'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_code', 'code');
    }
}
