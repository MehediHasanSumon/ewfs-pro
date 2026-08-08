<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollExtra extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'payroll_item_id',
        'voucher_transaction_type_id',
        'amount',
        'remarks',
        'payment_voucher_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function voucherTransactionType(): BelongsTo
    {
        return $this->belongsTo(VoucherTransactionType::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'payment_voucher_id');
    }

    public function voucherLinks(): HasMany
    {
        return $this->hasMany(PayrollVoucherLink::class);
    }
}
