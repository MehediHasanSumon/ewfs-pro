<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'account_id',
        'code',
        'name',
        'mobile',
        'email',
        'nid_number',
        'vat_reg_no',
        'tin_no',
        'trade_license',
        'discount_rate',
        'security_deposit',
        'credit_limit',
        'address',
        'status'
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'status' => 'boolean'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
