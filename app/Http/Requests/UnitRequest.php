<?php

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $unitId = $unit instanceof Unit ? $unit->getKey() : $unit;

        return [
            'code' => ['nullable', 'string', 'max:32', Rule::unique('units', 'code')->ignore($unitId)],
            'name' => ['required', 'string', 'max:100', Rule::unique('units', 'name')->ignore($unitId)],
            'value' => ['required', 'string', 'max:50'],
            'quantity_scale' => ['sometimes', 'integer', 'between:0,6'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
