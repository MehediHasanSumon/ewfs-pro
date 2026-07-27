<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_date',
        'sale_time',
        'invoice_no',
        'memo_no',
        'shift_id',
        'transaction_id',
        'customer',
        'mobile_number',
        'vehicle_no',
        'product_id',
        'category_code',
        'purchase_price',
        'quantity',
        'amount',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'remarks',
        'status'
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
        'status' => 'boolean'
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
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }

    public function batches()
    {
        return $this->hasMany(SaleBatch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_code', 'code');
    }
}