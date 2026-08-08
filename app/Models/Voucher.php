<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Voucher extends Model
{
    protected $appends = [
        'date',
        'from_account_id',
        'to_account_id',
        'payment_method',
        'amount',
    ];

    protected $fillable = [
        'voucher_no',
        'voucher_type',
        'voucher_date',
        'voucher_time',
        'shift_id',
        'voucher_category_id',
        'voucher_transaction_type_id',
        'payment_sub_type_id',
        'journal_entry_id',
        'status',
        'external_reference',
        'description',
        'remarks',
        'created_by',
        'posted_by',
        'posted_at',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('voucher_type', $type);
    }

    public function voucherCategory(): BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class);
    }

    public function paymentSubType(): BelongsTo
    {
        return $this->voucherTransactionType();
    }

    public function voucherTransactionType(): BelongsTo
    {
        return $this->belongsTo(
            VoucherTransactionType::class,
            'voucher_transaction_type_id'
        );
    }

    public function getPaymentSubTypeIdAttribute(): ?int
    {
        $id = $this->attributes['voucher_transaction_type_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function setPaymentSubTypeIdAttribute(int|string|null $value): void
    {
        $this->attributes['voucher_transaction_type_id'] = $value;
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class)->orderBy('line_no');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function fromAccount(): HasOneThrough
    {
        return $this->hasOneThrough(
            Account::class,
            VoucherLine::class,
            'voucher_id',
            'id',
            'id',
            'account_id'
        )->where('voucher_lines.entry_side', $this->voucher_type === 'receipt' ? 'credit' : 'credit');
    }

    public function toAccount(): HasOneThrough
    {
        return $this->hasOneThrough(
            Account::class,
            VoucherLine::class,
            'voucher_id',
            'id',
            'id',
            'account_id'
        )->where('voucher_lines.entry_side', 'debit');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(VoucherLine::class)
            ->where('entry_side', 'debit')
            ->with(['account', 'paymentDetail']);
    }

    public function salaryPayment(): HasOne
    {
        return $this->hasOne(
            EmployeeSalaryPayment::class,
            'payment_voucher_id'
        );
    }

    public function payrollLink(): HasOne
    {
        return $this->hasOne(PayrollVoucherLink::class);
    }

    public function getDateAttribute(): ?string
    {
        return $this->voucher_date?->format('Y-m-d');
    }

    public function getFromAccountIdAttribute(): ?int
    {
        return $this->linesForSide('credit')->first()?->account_id;
    }

    public function getToAccountIdAttribute(): ?int
    {
        return $this->linesForSide('debit')->first()?->account_id;
    }

    public function getPaymentMethodAttribute(): ?string
    {
        $line = $this->linesForSide('debit')->first();

        return $line?->paymentDetail?->payment_method;
    }

    public function getAmountAttribute(): float
    {
        return (float) ($this->linesForSide('debit')->sum('amount') ?? 0);
    }

    private function linesForSide(string $side)
    {
        return $this->relationLoaded('lines')
            ? $this->lines->where('entry_side', $side)
            : $this->lines()->where('entry_side', $side)->with('paymentDetail')->get();
    }
}
