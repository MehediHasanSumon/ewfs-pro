<?php

namespace App\Http\Requests;

use App\Rules\AllowedDispenserProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where('status', true),
            ],
            'dispenser_readings' => ['required', 'array', 'min:1'],
            'dispenser_readings.*.dispenser_id' => [
                'required',
                'integer',
                'distinct',
                'exists:dispensers,id',
            ],
            'dispenser_readings.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
                new AllowedDispenserProductCategory,
            ],
            'dispenser_readings.*.start_reading' => [
                'required',
                'numeric',
                'min:0',
            ],
            'dispenser_readings.*.end_reading' => [
                'required',
                'numeric',
                'gte:dispenser_readings.*.start_reading',
            ],
            'dispenser_readings.*.meter_test' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'dispenser_readings.*.item_rate' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'dispenser_readings.*.reading_by' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('status', true),
            ],
            'other_product_sales' => ['nullable', 'array'],
            'other_product_sales.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('status', true),
            ],
            'other_product_sales.*.quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
            'other_product_sales.*.recorded_quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'other_product_sales.*.employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('status', true),
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
