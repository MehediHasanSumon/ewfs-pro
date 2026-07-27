<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenserReading extends Model
{
    protected $fillable = [
        'transaction_date',
        'shift_id',
        'employee_id',
        'dispenser_id',
        'product_id',
        'start_reading',
        'end_reading',
        'meter_test',
        'net_reading',
        'item_rate',
        'total_sale',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
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
}
