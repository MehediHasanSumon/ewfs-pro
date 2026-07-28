<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDailyBalance extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'balance_date' => 'date',
            'opening_balance' => 'decimal:4',
            'debit_total' => 'decimal:4',
            'credit_total' => 'decimal:4',
            'closing_balance' => 'decimal:4',
            'refreshed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
