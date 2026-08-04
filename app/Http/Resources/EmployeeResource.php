<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'employee_name' => $this->employee_name,
            'payment_account_id' => $this->payment_account_id,
            'payment_account_group_id' => $this->whenLoaded(
                'paymentAccount',
                fn () => $this->paymentAccount?->group_id
            ),
            'email' => $this->email,
            'mobile' => $this->mobile,
            'mobile_two' => $this->mobile_two,
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'emp_type_id' => $this->emp_type_id,
            'dob' => $this->dob?->format('Y-m-d'),
            'gender' => $this->gender,
            'blood_group' => $this->blood_group,
            'marital_status' => $this->marital_status,
            'religion' => $this->religion,
            'nid' => $this->nid,
            'emergency_contact_person' => $this->emergency_contact_person,
            'emergency_contact_number' => $this->emergency_contact_number,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'job_status' => $this->job_status,
            'salary' => (float) ($this->salary ?? 0),
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'highest_education' => $this->highest_education,
            'status' => (bool) $this->status,
            'photo_path' => $this->photo,
            'photo_url' => $this->fileUrl($this->photo),
            'signature_path' => $this->signature,
            'signature_url' => $this->fileUrl($this->signature),
            'nid_document_path' => $this->nid_document_path,
            'nid_document_url' => $this->fileUrl($this->nid_document_path),
            'salary_structure' => $this->whenLoaded(
                'salaryStructure',
                fn () => $this->salaryStructure ? [
                    'basic_salary' => (float) $this->salaryStructure->basic_salary,
                    'home_rent_percent' => (float) $this->salaryStructure->home_rent_percent,
                    'home_rent_amount' => (float) $this->salaryStructure->home_rent_amount,
                    'medical_percent' => (float) $this->salaryStructure->medical_percent,
                    'medical_amount' => (float) $this->salaryStructure->medical_amount,
                    'conveyance_percent' => (float) $this->salaryStructure->conveyance_percent,
                    'conveyance_amount' => (float) $this->salaryStructure->conveyance_amount,
                    'other_allowances' => (float) $this->salaryStructure->other_allowances,
                    'deductions' => (float) $this->salaryStructure->deductions,
                    'gross_salary' => (float) $this->salaryStructure->gross_salary,
                ] : null
            ),
        ];
    }

    private function fileUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(config('erp.employee_uploads.disk', 'public'))
            ->url(ltrim($path, '/'));
    }
}
