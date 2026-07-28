<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftClosingSummary extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fuel_sales' => 'decimal:4',
            'other_product_sales' => 'decimal:4',
            'credit_sales' => 'decimal:4',
            'bank_sales' => 'decimal:4',
            'cash_sales' => 'decimal:4',
            'cash_receipts' => 'decimal:4',
            'bank_receipts' => 'decimal:4',
            'cash_payments' => 'decimal:4',
            'bank_payments' => 'decimal:4',
            'office_payments' => 'decimal:4',
            'expected_cash' => 'decimal:4',
            'actual_cash' => 'decimal:4',
            'variance_amount' => 'decimal:4',
            'refreshed_at' => 'datetime',
        ];
    }

    public function closing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }
}
