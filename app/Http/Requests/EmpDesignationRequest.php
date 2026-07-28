<?php

namespace App\Http\Requests;

use App\Models\EmpDesignation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $designation = $this->route('empDesignation');
        $designationId = $designation instanceof EmpDesignation ? $designation->getKey() : $designation;

        return [
            'code' => ['nullable', 'string', 'max:32', Rule::unique('emp_designations', 'code')->ignore($designationId)],
            'name' => ['required', 'string', 'max:100', Rule::unique('emp_designations', 'name')->ignore($designationId)],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
