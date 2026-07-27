<?php

namespace App\Helpers;

use App\Models\Customer;

class CustomerHelper
{
    public static function generateCustomerCode(): string
    {
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        
        if (!$lastCustomer || !$lastCustomer->code) {
            return 'CC01';
        }
        
        $lastCode = $lastCustomer->code;
        $number = (int) substr($lastCode, 2);
        $newNumber = $number + 1;
        
        return 'CC' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
}