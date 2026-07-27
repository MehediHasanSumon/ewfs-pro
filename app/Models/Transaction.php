<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'ac_number',
        'transaction_id',
        'transaction_type',
        'amount',
        'description',
        'transaction_date',
        'transaction_time',
        'payment_type',
        'bank_name',
        'branch_name',
        'account_number',
        'cheque_type',
        'cheque_no',
        'cheque_date',
        'mobile_bank_name',
        'mobile_number'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'transaction_time' => 'datetime:H:i:s',
        'cheque_date' => 'date'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class, 'transaction_id', 'transaction_id');
    }
}