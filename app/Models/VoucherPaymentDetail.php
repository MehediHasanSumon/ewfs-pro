<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherPaymentDetail extends Model
{
    protected $fillable = [
        'voucher_line_id',
        'payment_method',
        'bank_type',
        'bank_name',
        'branch_name',
        'account_number',
        'cheque_number',
        'cheque_date',
        'mobile_bank_name',
        'mobile_number',
        'transaction_reference',
    ];

    protected function casts(): array
    {
        return ['cheque_date' => 'date'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(VoucherLine::class, 'voucher_line_id');
    }
}
