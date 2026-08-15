<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transfer_no',
        'transfer_date',
        'from_account_id',
        'to_account_id',
        'amount',
        'transfer_fee',
        'fee_account_id',
        'journal_entry_id',
        'reference_no',
        'remarks',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount' => 'decimal:4',
            'transfer_fee' => 'decimal:4',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function feeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fee_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeBetweenDates(
        Builder $query,
        CarbonInterface|string|null $startDate,
        CarbonInterface|string|null $endDate
    ): Builder {
        return $query
            ->when($startDate, fn (Builder $q) => $q->whereDate('transfer_date', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('transfer_date', '<=', $endDate));
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where(function (Builder $q) use ($accountId): void {
            $q->where('from_account_id', $accountId)
                ->orWhere('to_account_id', $accountId);
        });
    }

    public function getTotalDeductionAttribute(): float
    {
        return (float) $this->amount + (float) $this->transfer_fee;
    }
}
