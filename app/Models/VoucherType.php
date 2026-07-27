<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherType extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }
}
