<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSubType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'voucher_category_id',
        'type',
        'report_bucket_code',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function voucherCategory()
    {
        return $this->belongsTo(VoucherCategory::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }
}
