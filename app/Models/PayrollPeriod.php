<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PayrollPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payroll_code',
        'month',
        'year',
        'remarks',
        'status',
        'payable_date',
        'started_at',
        'generated_at',
        'completed_at',
        'locked_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'payable_date' => 'date',
            'started_at' => 'datetime',
            'generated_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollPeriod $period): void {
            if (
                $period->getOriginal('status') === self::STATUS_PAID
                && $period->isDirty([
                    'month',
                    'year',
                    'remarks',
                    'payable_date',
                ])
            ) {
                throw ValidationException::withMessages([
                    'payroll' => 'Completed payroll history is immutable.',
                ]);
            }
        });
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PayrollSnapshot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_PROCESSING,
            self::STATUS_GENERATED,
        ]);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_GENERATED);
    }

    public function label(): string
    {
        return now()->setDate($this->year, $this->month, 1)->format('F Y');
    }

    public function hasPostedPayments(): bool
    {
        return $this->items()
            ->whereHas('voucherLinks', fn (Builder $query) => $query
                ->where('status', PayrollVoucherLink::STATUS_POSTED))
            ->exists();
    }

    public function hasVoucherHistory(): bool
    {
        return $this->items()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('voucherLinks')
                    ->orWhereNotNull('payment_voucher_id')
                    ->orWhereNotNull('advance_adjustment_voucher_id')
                    ->orWhereHas(
                        'extras',
                        fn (Builder $extras) => $extras
                            ->whereNotNull('payment_voucher_id')
                    );
            })
            ->exists();
    }
}
