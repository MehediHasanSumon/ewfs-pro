<?php

namespace App\Http\Requests;

use App\Models\EmpDepartment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $department = $this->route('empDepartment');
        $departmentId = $department instanceof EmpDepartment ? $department->getKey() : $department;

        return [
            'emp_type_id' => ['nullable', 'integer', 'exists:emp_types,id'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('emp_departments', 'code')->ignore($departmentId)],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('emp_departments', 'name')
                    ->where(function ($query) {
                        return $this->filled('emp_type_id')
                            ? $query->where('emp_type_id', $this->integer('emp_type_id'))
                            : $query->whereNull('emp_type_id');
                    })
                    ->ignore($departmentId),
            ],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
