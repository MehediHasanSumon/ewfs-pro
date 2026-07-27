<?php

namespace App\Helpers;

use App\Models\Account;

class AccountHelper
{
    public static function generateAccountNumber(): string
    {
        $lastAccount = Account::orderBy('ac_number', 'desc')->first();
        
        if ($lastAccount && str_starts_with($lastAccount->ac_number, '1')) {
            return str_pad((int)$lastAccount->ac_number + 1, 6, '0', STR_PAD_LEFT);
        }
        
        return '100001';
    }
}