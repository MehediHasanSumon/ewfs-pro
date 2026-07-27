<?php

namespace App\Helpers;

use App\Models\Voucher;

class VoucherHelper
{
    public static function generateVoucherNo(): string
    {
        $lastVoucher = Voucher::orderBy('id', 'desc')->first();
        
        if (!$lastVoucher) {
            return 'V0001';
        }
        
        $lastNumber = (int) substr($lastVoucher->voucher_no, 1);
        $nextNumber = $lastNumber + 1;
        
        return 'V' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}