<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'fax',
        'company_email',
        'trade_license',
        'tin_no',
        'bin_no',
        'vat_no',
        'vat_rate',
        'currency',
        'company_logo',
        'pdf_watermark_image',
        'is_registration',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'is_registration' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
