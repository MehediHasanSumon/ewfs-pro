<?php

namespace App\Http\Requests;

use App\Models\Dispenser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispenserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dispenser = $this->route('dispenser');
        $dispenserId = $dispenser instanceof Dispenser ? $dispenser->getKey() : $dispenser;

        return [
            'code' => ['nullable', 'string', 'max:64', Rule::unique('dispensers', 'code')->ignore($dispenserId)],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'dispenser_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('dispensers', 'dispenser_name')
                    ->where(fn ($query) => $query->where('product_id', $this->integer('product_id')))
                    ->ignore($dispenserId),
            ],
            'opening_reading' => ['nullable', 'numeric', 'min:0'],
            'item_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function attributesForPersistence(): array
    {
        return $this->safe()->except('item_rate');
    }
}
