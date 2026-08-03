<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftClosingProductItem extends Model
{
    protected $fillable = [
        'shift_closing_id',
        'product_id',
        'unit_id',
        'employee_id',
        'sale_item_id',
        'inventory_movement_id',
        'product_name_snapshot',
        'unit_name_snapshot',
        'unit_price',
        'quantity',
        'recorded_quantity',
        'quantity_variance',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:6',
            'quantity' => 'decimal:6',
            'recorded_quantity' => 'decimal:6',
            'quantity_variance' => 'decimal:6',
            'line_total' => 'decimal:4',
        ];
    }

    public function closing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
