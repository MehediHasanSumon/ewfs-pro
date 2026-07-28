<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyOpeningBalance extends Model
{
    protected $fillable = [
        'customer_id',
        'supplier_id',
        'employee_id',
        'balance_type',
        'effective_date',
        'amount',
        'journal_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'amount' => 'decimal:4'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
