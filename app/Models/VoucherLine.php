<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VoucherLine extends Model
{
    protected $appends = [
        'payment_type',
    ];

    protected $fillable = [
        'voucher_id',
        'line_no',
        'account_id',
        'entry_side',
        'amount',
        'customer_id',
        'supplier_id',
        'employee_id',
        'description',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
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

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(VoucherPaymentDetail::class);
    }

    public function getPaymentTypeAttribute(): ?string
    {
        return $this->relationLoaded('paymentDetail')
            ? $this->paymentDetail?->payment_method
            : $this->paymentDetail()->value('payment_method');
    }
}
