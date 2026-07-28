<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    public const UPDATED_AT = null;

    protected $appends = [
        'ac_number',
        'transaction_type',
        'amount',
        'payment_type',
        'transaction_date',
        'transaction_time',
    ];

    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'account_id',
        'debit_amount',
        'credit_amount',
        'customer_id',
        'supplier_id',
        'employee_id',
        'product_id',
        'payment_method',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
        ];
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereHas('entry', fn (Builder $entry) => $entry
            ->accountingEffective()
            ->whereBetween('business_date', [$from, $to]));
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getAcNumberAttribute(): ?string
    {
        return $this->relationLoaded('account')
            ? $this->account?->ac_number
            : $this->account()->value('ac_number');
    }

    public function getTransactionTypeAttribute(): string
    {
        return (float) $this->debit_amount > 0 ? 'Dr' : 'Cr';
    }

    public function getAmountAttribute(): float
    {
        return (float) max((float) $this->debit_amount, (float) $this->credit_amount);
    }

    public function getPaymentTypeAttribute(): ?string
    {
        return $this->payment_method;
    }

    public function getTransactionDateAttribute(): ?string
    {
        $entry = $this->relationLoaded('entry') ? $this->entry : $this->entry()->first();

        return $entry?->business_date?->format('Y-m-d');
    }

    public function getTransactionTimeAttribute(): ?string
    {
        $entry = $this->relationLoaded('entry') ? $this->entry : $this->entry()->first();

        return $entry?->occurred_at?->format('H:i:s');
    }
}
