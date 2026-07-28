<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePaymentDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'account_id',
        'payment_method',
        'bank_type',
        'bank_name',
        'branch_name',
        'account_number',
        'cheque_number',
        'cheque_date',
        'mobile_bank_name',
        'mobile_number',
    ];

    protected function casts(): array
    {
        return [
            'cheque_date' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
