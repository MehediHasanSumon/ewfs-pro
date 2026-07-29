<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $imageMax = (int) config('erp.employee_uploads.image_max_kb', 5120);
        $nidMax = (int) config('erp.employee_uploads.nid_max_kb', 10240);

        return [
            'employee_code' => ['prohibited'],
            'salary' => ['prohibited'],
            'employee_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'emp_type_id' => ['nullable', 'exists:emp_types,id'],
            'department_id' => ['nullable', 'exists:emp_departments,id'],
            'designation_id' => ['nullable', 'exists:emp_designations,id'],
            'mobile' => ['nullable', 'string', 'max:100'],
            'mobile_two' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:10'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'religion' => ['nullable', 'string', 'max:100'],
            'nid' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person' => ['nullable', 'string', 'max:100'],
            'emergency_contact_number' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'present_address' => ['nullable', 'string', 'max:250'],
            'permanent_address' => ['nullable', 'string', 'max:350'],
            'job_status' => ['nullable', 'string', 'max:50'],
            'joining_date' => ['nullable', 'date'],
            'order' => ['nullable', 'integer'],
            'highest_education' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
            'photo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max($imageMax),
            ],
            'signature' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max($imageMax),
            ],
            'nid_document' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max($nidMax),
            ],
            'remove_photo' => ['sometimes', 'boolean'],
            'remove_signature' => ['sometimes', 'boolean'],
            'remove_nid_document' => ['sometimes', 'boolean'],
            'salary_structure' => ['required', 'array'],
            'salary_structure.basic_salary' => ['required', 'numeric', 'gt:0'],
            'salary_structure.home_rent_percent' => ['nullable', 'numeric', 'between:0,100'],
            'salary_structure.medical_percent' => ['nullable', 'numeric', 'between:0,100'],
            'salary_structure.conveyance_percent' => ['nullable', 'numeric', 'between:0,100'],
            'salary_structure.other_allowances' => ['nullable', 'numeric', 'min:0'],
            'salary_structure.deductions' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.prohibited' => 'Employee Code is generated automatically.',
            'salary.prohibited' => 'Salary is calculated from the salary structure.',
            'salary_structure.required' => 'Configure the employee salary structure.',
            'salary_structure.basic_salary.required' => 'Basic Salary is required.',
            'salary_structure.basic_salary.gt' => 'Basic Salary must be greater than zero.',
        ];
    }
}
