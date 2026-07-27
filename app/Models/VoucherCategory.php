<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function paymentSubTypes()
    {
        return $this->hasMany(PaymentSubType::class);
    }
}
