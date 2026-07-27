<?php

namespace App\Helpers;

use App\Models\Transaction;

class TransactionHelper
{
    public static function generateTransactionId(): string
    {
        do {
            $transactionId = self::generateRandomString(9);
            $exists = Transaction::where('transaction_id', $transactionId)->exists();
        } while ($exists);

        return $transactionId;
    }

    private static function generateRandomString(int $length): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
}