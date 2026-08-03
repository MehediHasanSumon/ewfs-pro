<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'shift_id',
        'journal_entry_id',
        'business_date',
        'occurred_at',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'before_stock',
        'after_stock',
        'unit_cost',
        'total_cost',
        'source_type',
        'source_id',
        'source_line_id',
        'remarks',
        'reversal_of_id',
        'idempotency_key',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'occurred_at' => 'datetime',
            'quantity_in' => 'decimal:6',
            'quantity_out' => 'decimal:6',
            'before_stock' => 'decimal:6',
            'after_stock' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:4',
        ];
    }

    public function scopeForProductThrough(Builder $query, int $productId, string $date): Builder
    {
        return $query->where('product_id', $productId)->whereDate('business_date', '<=', $date);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
