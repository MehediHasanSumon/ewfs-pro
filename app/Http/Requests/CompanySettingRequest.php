<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_details' => ['nullable', 'string'],
            'proprietor_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string'],
            'factory_address' => ['nullable', 'string'],
            'company_mobile' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'trade_license' => ['nullable', 'string', 'max:255'],
            'tin_no' => ['nullable', 'string', 'max:255'],
            'bin_no' => ['nullable', 'string', 'max:255'],
            'vat_no' => ['nullable', 'string', 'max:255'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'pdf_watermark_image' => ['nullable', 'image', 'max:5120'],
            'remove_pdf_watermark' => ['nullable', 'boolean'],
            'is_registration' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesForPersistence(): array
    {
        $attributes = $this->safe()->except(['company_logo', 'pdf_watermark_image', 'remove_pdf_watermark']);
        $attributes['currency'] = strtoupper($attributes['currency'] ?? 'BDT');

        return $attributes;
    }
}
