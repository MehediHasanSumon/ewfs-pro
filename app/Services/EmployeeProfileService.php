<?php

namespace App\Services;

use App\Helpers\VoucherHelper;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EmployeeProfileService
{
    private const FILE_FIELDS = [
        'photo' => 'photos',
        'signature' => 'signatures',
        'nid_document' => 'nid-documents',
    ];

    public function __construct(
        private readonly PartyAccountService $partyAccounts,
        private readonly VoucherHelper $vouchers,
        private readonly SalaryStructureService $salaries
    ) {}

    public function create(array $validated): Employee
    {
        $storedFiles = $this->storeUploadedFiles($validated);

        try {
            $employee = DB::transaction(function () use ($validated, $storedFiles): Employee {
                $status = (bool) $validated['status'];
                $salaryStructure = $this->salaries->calculate($validated['salary_structure']);
                $account = $this->partyAccounts->createEmployeeAccount(
                    $validated['employee_name'],
                    $status
                );

                $employee = Employee::query()->create([
                    ...$this->employeePayload($validated, $status),
                    'account_id' => $account->id,
                    'employee_code' => $this->vouchers->generateEmployeeCode(),
                    'salary' => $salaryStructure['gross_salary'],
                    ...$this->employeeFilePayload($storedFiles),
                ]);

                $employee->salaryStructure()->create($salaryStructure);

                return $employee;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles(array_values($storedFiles));
            throw $exception;
        }

        return $employee->load('salaryStructure');
    }

    public function update(Employee $employee, array $validated): Employee
    {
        $storedFiles = $this->storeUploadedFiles($validated);
        $filesToDelete = [];

        try {
            DB::transaction(function () use (
                $employee,
                $validated,
                $storedFiles,
                &$filesToDelete
            ): void {
                $status = (bool) $validated['status'];
                $salaryStructure = $this->salaries->calculate($validated['salary_structure']);

                $employee->loadMissing('account');
                $employee->account?->update([
                    'name' => $validated['employee_name'],
                    'status' => $status,
                ]);

                $filePayload = $this->updatedFilePayload(
                    $employee,
                    $validated,
                    $storedFiles,
                    $filesToDelete
                );

                $employee->update([
                    ...$this->employeePayload($validated, $status),
                    'salary' => $salaryStructure['gross_salary'],
                    ...$filePayload,
                ]);

                $employee->salaryStructure()->updateOrCreate([], $salaryStructure);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles(array_values($storedFiles));
            throw $exception;
        }

        $this->deleteStoredFiles($filesToDelete);

        return $employee->refresh()->load('salaryStructure');
    }

    public function filePaths(Employee $employee): array
    {
        return array_values(array_filter([
            $employee->photo,
            $employee->signature,
            $employee->nid_document_path,
        ]));
    }

    public function deleteStoredFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk($this->disk())->delete(
                array_map(fn (string $path) => ltrim($path, '/'), $paths)
            );
        }
    }

    private function employeePayload(array $validated, bool $status): array
    {
        return [
            'payment_account_id' => $validated['payment_account_id'] ?? null,
            'emp_type_id' => $validated['emp_type_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'employee_name' => $validated['employee_name'],
            'email' => $validated['email'] ?? null,
            'order' => $validated['order'] ?? 1,
            'dob' => $validated['dob'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'emergency_contact_person' => $validated['emergency_contact_person'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'nid' => $validated['nid'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'mobile_two' => $validated['mobile_two'] ?? null,
            'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'present_address' => $validated['present_address'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'job_status' => $validated['job_status'] ?? null,
            'joining_date' => $validated['joining_date'] ?? null,
            'highest_education' => $validated['highest_education'] ?? null,
            'status' => $status,
        ];
    }

    private function storeUploadedFiles(array $validated): array
    {
        $stored = [];

        foreach (self::FILE_FIELDS as $field => $directory) {
            $file = $validated[$field] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store(
                trim((string) config('erp.employee_uploads.directory', 'employees'), '/')
                    .'/'.$directory,
                $this->disk()
            );

            if ($path === false) {
                $this->deleteStoredFiles(array_values($stored));
                throw new RuntimeException("Unable to store employee {$field}.");
            }

            $stored[$field] = $path;
        }

        return $stored;
    }

    private function employeeFilePayload(array $storedFiles): array
    {
        return [
            'photo' => $storedFiles['photo'] ?? null,
            'signature' => $storedFiles['signature'] ?? null,
            'nid_document_path' => $storedFiles['nid_document'] ?? null,
        ];
    }

    private function updatedFilePayload(
        Employee $employee,
        array $validated,
        array $storedFiles,
        array &$filesToDelete
    ): array {
        $payload = [];
        $columns = [
            'photo' => 'photo',
            'signature' => 'signature',
            'nid_document' => 'nid_document_path',
        ];

        foreach ($columns as $field => $column) {
            $currentPath = $employee->{$column};

            if (isset($storedFiles[$field])) {
                $payload[$column] = $storedFiles[$field];

                if ($currentPath) {
                    $filesToDelete[] = $currentPath;
                }

                continue;
            }

            if ((bool) ($validated['remove_'.$field] ?? false)) {
                $payload[$column] = null;

                if ($currentPath) {
                    $filesToDelete[] = $currentPath;
                }
            }
        }

        return $payload;
    }

    private function disk(): string
    {
        return (string) config('erp.employee_uploads.disk', 'public');
    }
}
