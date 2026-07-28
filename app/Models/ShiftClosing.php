<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftClosing extends Model
{
    protected $fillable = [
        'business_date',
        'shift_id',
        'status',
        'expected_cash',
        'actual_cash',
        'variance_amount',
        'journal_entry_id',
        'created_by',
        'closed_by',
        'closed_at',
        'reversal_of_id',
        'lock_version',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'expected_cash' => 'decimal:4',
            'actual_cash' => 'decimal:4',
            'variance_amount' => 'decimal:4',
            'closed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function dispenserReadings(): HasMany
    {
        return $this->hasMany(DispenserReading::class);
    }

    public function productItems(): HasMany
    {
        return $this->hasMany(ShiftClosingProductItem::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(ShiftClosingSummary::class);
    }
}
