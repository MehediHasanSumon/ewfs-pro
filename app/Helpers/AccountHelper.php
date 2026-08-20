<?php

namespace App\Helpers;

use App\Models\Account;

class AccountHelper
{
    public static function generateAccountNumber(): string
    {
        $maxNum = Account::query()
            ->whereRaw("ac_number REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(ac_number AS UNSIGNED)) as max_ac')
            ->value('max_ac');

        if ($maxNum && $maxNum >= 100000) {
            return (string) ($maxNum + 1);
        }

        return '100001';
    }
}