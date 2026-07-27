<?php

namespace App\Helpers;

use App\Models\SaleBatch;

class BatchHelper
{
    public static function generateBatchCode(): string
    {
        do {
            $batchCode = 'BATCH' . self::generateRandomString(8);
            $exists = SaleBatch::where('batch_code', $batchCode)->exists();
        } while ($exists);

        return $batchCode;
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