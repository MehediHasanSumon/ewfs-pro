<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenserReading extends Model
{
    protected $appends = [
        'transaction_date',
        'shift_id',
        'net_reading',
        'item_rate',
        'total_sale',
    ];

    protected $fillable = [
        'shift_closing_id',
        'dispenser_id',
        'product_id',
        'employee_id',
        'start_reading',
        'end_reading',
        'meter_test',
        'net_quantity',
        'unit_price',
        'gross_amount',
        'inventory_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'start_reading' => 'decimal:6',
            'end_reading' => 'decimal:6',
            'meter_test' => 'decimal:6',
            'net_quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:4',
        ];
    }

    public function closing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(Dispenser::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    public function getTransactionDateAttribute(): ?string
    {
        $closing = $this->relationLoaded('closing') ? $this->closing : $this->closing()->first();

        return $closing?->business_date?->format('Y-m-d');
    }

    public function getShiftIdAttribute(): ?int
    {
        $closing = $this->relationLoaded('closing') ? $this->closing : $this->closing()->first();

        return $closing?->shift_id;
    }

    public function getNetReadingAttribute(): float
    {
        return (float) $this->net_quantity;
    }

    public function getItemRateAttribute(): float
    {
        return (float) $this->unit_price;
    }

    public function getTotalSaleAttribute(): float
    {
        return (float) $this->gross_amount;
    }
}
