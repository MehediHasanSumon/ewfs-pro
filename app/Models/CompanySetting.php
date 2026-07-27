<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_details',
        'proprietor_name',
        'company_address',
        'factory_address',
        'company_mobile',
        'company_phone',
        'company_email',
        'trade_license',
        'tin_no',
        'bin_no',
        'vat_no',
        'vat_rate',
        'currency',
        'company_logo',
        'is_registration',
        'status',
    ];
}
