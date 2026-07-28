<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditSaleItem extends Model
{
    protected $fillable = [
        'credit_sale_customer_id',
        'line_no',
        'vehicle_id',
        'product_id',
        'category_id',
        'unit_id',
        'vehicle_number_snapshot',
        'product_code_snapshot',
        'product_name_snapshot',
        'category_name_snapshot',
        'unit_name_snapshot',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'discount_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function customerAllocation(): BelongsTo
    {
        return $this->belongsTo(CreditSaleCustomer::class, 'credit_sale_customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
