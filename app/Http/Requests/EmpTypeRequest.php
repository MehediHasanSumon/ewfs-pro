<?php

namespace App\Http\Requests;

use App\Models\EmpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empType = $this->route('empType');
        $empTypeId = $empType instanceof EmpType ? $empType->getKey() : $empType;

        return [
            'code' => ['nullable', 'string', 'max:32', Rule::unique('emp_types', 'code')->ignore($empTypeId)],
            'name' => ['required', 'string', 'max:100', Rule::unique('emp_types', 'name')->ignore($empTypeId)],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
