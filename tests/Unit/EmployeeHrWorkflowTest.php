<?php

use App\Helpers\VoucherHelper;
use App\Http\Requests\EmployeeRequest;
use App\Models\Account;
use App\Services\DocumentNumberService;
use App\Services\EmployeeProfileService;
use App\Services\PartyAccountService;
use App\Services\SalaryStructureService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('accounts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id')->nullable();
        $table->string('employee_code')->unique();
        $table->string('employee_name');
        $table->string('email')->nullable();
        $table->unsignedBigInteger('emp_type_id')->nullable();
        $table->unsignedBigInteger('department_id')->nullable();
        $table->unsignedBigInteger('designation_id')->nullable();
        $table->unsignedInteger('order')->default(1);
        $table->date('dob')->nullable();
        $table->string('gender')->nullable();
        $table->string('blood_group')->nullable();
        $table->string('marital_status')->nullable();
        $table->string('emergency_contact_person')->nullable();
        $table->string('religion')->nullable();
        $table->string('nid')->nullable();
        $table->string('mobile')->nullable();
        $table->string('mobile_two')->nullable();
        $table->string('emergency_contact_number')->nullable();
        $table->string('father_name')->nullable();
        $table->string('mother_name')->nullable();
        $table->string('present_address')->nullable();
        $table->string('permanent_address')->nullable();
        $table->string('job_status')->nullable();
        $table->decimal('salary', 24, 4)->nullable();
        $table->date('joining_date')->nullable();
        $table->boolean('status')->default(true);
        $table->string('photo')->nullable();
        $table->string('signature')->nullable();
        $table->string('nid_document_path')->nullable();
        $table->string('highest_education')->nullable();
        $table->timestamps();
    });

    Schema::create('employee_salary_structures', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employee_id')->unique();
        $table->decimal('basic_salary', 24, 4);
        $table->decimal('home_rent_percent', 8, 4)->default(0);
        $table->decimal('home_rent_amount', 24, 4)->default(0);
        $table->decimal('medical_percent', 8, 4)->default(0);
        $table->decimal('medical_amount', 24, 4)->default(0);
        $table->decimal('conveyance_percent', 8, 4)->default(0);
        $table->decimal('conveyance_amount', 24, 4)->default(0);
        $table->decimal('other_allowances', 24, 4)->default(0);
        $table->decimal('deductions', 24, 4)->default(0);
        $table->decimal('gross_salary', 24, 4);
        $table->timestamps();
    });

    Schema::create('document_sequences', function (Blueprint $table) {
        $table->id();
        $table->string('document_type');
        $table->string('prefix')->nullable();
        $table->unsignedSmallInteger('fiscal_year');
        $table->unsignedBigInteger('next_number')->default(1);
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamps();
        $table->unique(['document_type', 'fiscal_year']);
    });

    DB::table('accounts')->insert([
        'id' => 1,
        'name' => 'Employee Account',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Storage::fake('public');
    config()->set('erp.employee_uploads.disk', 'public');
    config()->set('erp.employee_uploads.directory', 'employees');
});

function employeeProfileService(): EmployeeProfileService
{
    $account = Account::query()->findOrFail(1);
    $partyAccounts = Mockery::mock(PartyAccountService::class);
    $partyAccounts->shouldReceive('createEmployeeAccount')
        ->andReturn($account);

    return new EmployeeProfileService(
        $partyAccounts,
        new VoucherHelper(new DocumentNumberService),
        new SalaryStructureService
    );
}

function employeePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_name' => 'Test Employee',
        'status' => true,
        'photo' => UploadedFile::fake()->create(
            'employee.jpg',
            100,
            'image/jpeg'
        ),
        'signature' => UploadedFile::fake()->create(
            'signature.png',
            100,
            'image/png'
        ),
        'nid_document' => UploadedFile::fake()->create(
            'nid.pdf',
            100,
            'application/pdf'
        ),
        'salary_structure' => [
            'basic_salary' => 20000,
            'home_rent_percent' => 40,
            'medical_percent' => 10,
            'conveyance_percent' => 5,
            'other_allowances' => 0,
            'deductions' => 0,
        ],
    ], $overrides);
}

it('creates employee uploads, a sequential code, and normalized salary structure', function () {
    $service = employeeProfileService();
    $employee = $service->create(employeePayload());
    $secondEmployee = $service->create(employeePayload([
        'employee_name' => 'Second Employee',
        'photo' => null,
        'signature' => null,
        'nid_document' => null,
    ]));

    expect($employee->employee_code)->toBe('EMP000001')
        ->and($secondEmployee->employee_code)->toBe('EMP000002')
        ->and((float) $employee->salary)->toBe(31000.0)
        ->and((float) $employee->salaryStructure->gross_salary)->toBe(31000.0)
        ->and((float) $employee->salaryStructure->home_rent_amount)->toBe(8000.0)
        ->and((float) $employee->salaryStructure->medical_amount)->toBe(2000.0)
        ->and((float) $employee->salaryStructure->conveyance_amount)->toBe(1000.0);

    Storage::disk('public')->assertExists($employee->photo);
    Storage::disk('public')->assertExists($employee->signature);
    Storage::disk('public')->assertExists($employee->nid_document_path);
});

it('replaces and removes employee files without leaving old files behind', function () {
    $service = employeeProfileService();
    $employee = $service->create(employeePayload());
    $oldPhoto = $employee->photo;
    $oldSignature = $employee->signature;
    $oldNid = $employee->nid_document_path;

    $employee = $service->update($employee, employeePayload([
        'photo' => UploadedFile::fake()->create(
            'replacement.webp',
            100,
            'image/webp'
        ),
        'signature' => null,
        'nid_document' => null,
        'remove_signature' => true,
        'remove_nid_document' => true,
        'salary_structure' => [
            'basic_salary' => 25000,
            'home_rent_percent' => 20,
            'medical_percent' => 0,
            'conveyance_percent' => 0,
            'other_allowances' => 500,
            'deductions' => 500,
        ],
    ]));

    expect($employee->photo)->not->toBe($oldPhoto)
        ->and($employee->signature)->toBeNull()
        ->and($employee->nid_document_path)->toBeNull()
        ->and((float) $employee->salary)->toBe(30000.0);

    Storage::disk('public')->assertMissing($oldPhoto);
    Storage::disk('public')->assertMissing($oldSignature);
    Storage::disk('public')->assertMissing($oldNid);
    Storage::disk('public')->assertExists($employee->photo);
});

it('rejects browser supplied employee codes and salary values', function () {
    $request = new EmployeeRequest;
    $validator = Validator::make([
        'employee_code' => 'MANUAL-1',
        'salary' => 999999,
        'employee_name' => 'Test Employee',
        'status' => true,
        'salary_structure' => [
            'basic_salary' => 20000,
            'home_rent_percent' => 0,
            'medical_percent' => 0,
            'conveyance_percent' => 0,
            'other_allowances' => 0,
            'deductions' => 0,
        ],
    ], $request->rules(), $request->messages());

    expect($validator->errors()->has('employee_code'))->toBeTrue()
        ->and($validator->errors()->has('salary'))->toBeTrue();
});

it('does not allow deductions to produce a non-positive gross salary', function () {
    expect(fn () => (new SalaryStructureService)->calculate([
        'basic_salary' => 1000,
        'home_rent_percent' => 0,
        'medical_percent' => 0,
        'conveyance_percent' => 0,
        'other_allowances' => 0,
        'deductions' => 1000,
    ]))->toThrow(ValidationException::class);
});
