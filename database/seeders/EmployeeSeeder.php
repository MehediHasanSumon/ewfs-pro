<?php

namespace Database\Seeders;

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\EmpDepartment;
use App\Models\EmpDesignation;
use App\Models\Employee;
use App\Models\EmpType;
use App\Models\Group;
use App\Services\DocumentNumberService;
use App\Services\PaymentAccountService;
use App\Services\SalaryStructureService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeSeeder extends Seeder
{
    public function run(
        DocumentNumberService $numbers,
        SalaryStructureService $salaries,
        PaymentAccountService $paymentAccounts
    ): void {
        $this->call([
            EmpTypeSeeder::class,
            EmpDesignationSeeder::class,
            EmpDepartmentSeeder::class,
            GroupSeeder::class,
            AccountSeeder::class,
        ]);

        DB::transaction(function () use (
            $numbers,
            $salaries,
            $paymentAccounts
        ): void {
            $employees = collect($this->employees());
            $employeeGroup = Group::query()
                ->where(
                    'code',
                    AccountGroupHelper::code('employee_management')
                )
                ->where('status', true)
                ->first();

            if (! $employeeGroup) {
                throw new RuntimeException(
                    'Employee account group is missing. Run GroupSeeder before EmployeeSeeder.'
                );
            }

            $types = $this->activeMasterIds(
                EmpType::query(),
                $employees->pluck('employee_type')
            );
            $departments = $this->activeMasterIds(
                EmpDepartment::query(),
                $employees->pluck('department')
            );
            $designations = $this->activeMasterIds(
                EmpDesignation::query(),
                $employees->pluck('designation')
            );
            $paymentAccountMap = Account::query()
                ->with('group')
                ->whereIn(
                    'semantic_code',
                    $employees->pluck('payment_account')->unique()
                )
                ->where('status', true)
                ->get()
                ->keyBy('semantic_code');

            foreach ($employees as $index => $data) {
                $paymentAccount = $paymentAccountMap->get(
                    $data['payment_account']
                );

                if (
                    ! $paymentAccount
                    || $paymentAccounts->methodFor($paymentAccount) === null
                ) {
                    throw new RuntimeException(
                        "Payment account [{$data['payment_account']}] is unavailable. Run AccountSeeder before EmployeeSeeder."
                    );
                }

                $employee = $this->existingEmployee($data);
                $account = $employee?->account
                    ?? Account::query()
                        ->where(
                            'semantic_code',
                            $this->employeeAccountSemanticCode($data['code'])
                        )
                        ->first();

                if (! $account) {
                    $account = Account::query()->create([
                        'group_id' => $employeeGroup->id,
                        'ac_number' => $numbers->next('account', 'AC'),
                        'name' => $data['name'],
                        'semantic_code' => $this->employeeAccountSemanticCode(
                            $data['code']
                        ),
                        'currency' => 'BDT',
                        'is_control_account' => false,
                        'allow_manual_posting' => true,
                        'is_system' => false,
                        'status' => true,
                    ]);
                } else {
                    $account->update([
                        'group_id' => $employeeGroup->id,
                        'name' => $data['name'],
                        'semantic_code' => $account->semantic_code
                            ?? $this->employeeAccountSemanticCode(
                                $data['code']
                            ),
                        'status' => true,
                    ]);
                }

                $salaryStructure = $salaries->calculate(
                    $data['salary_structure']
                );
                $attributes = [
                    'account_id' => $account->id,
                    'payment_account_id' => $paymentAccount->id,
                    'emp_type_id' => $types->get($data['employee_type']),
                    'department_id' => $departments->get($data['department']),
                    'designation_id' => $designations->get(
                        $data['designation']
                    ),
                    'employee_code' => $data['code'],
                    'employee_name' => $data['name'],
                    'email' => $data['email'],
                    'order' => $index + 1,
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'blood_group' => $data['blood_group'],
                    'marital_status' => $data['marital_status'],
                    'emergency_contact_person' => $data['emergency_contact_person'],
                    'religion' => $data['religion'],
                    'nid' => $data['nid'],
                    'mobile' => $data['mobile'],
                    'mobile_two' => $data['mobile_two'],
                    'emergency_contact_number' => $data['emergency_contact_number'],
                    'father_name' => $data['father_name'],
                    'mother_name' => $data['mother_name'],
                    'present_address' => $data['present_address'],
                    'permanent_address' => $data['permanent_address'],
                    'job_status' => 'Permanent',
                    'salary' => $salaryStructure['gross_salary'],
                    'joining_date' => $data['joining_date'],
                    'status' => true,
                    'status_date' => $data['joining_date'],
                    'photo' => null,
                    'signature' => null,
                    'nid_document_path' => null,
                    'highest_education' => $data['highest_education'],
                    'reference_one_name' => $data['reference_name'],
                    'reference_one_phone' => $data['reference_phone'],
                    'reference_one_address' => $data['reference_address'],
                    'reference_two_name' => null,
                    'reference_two_phone' => null,
                    'reference_two_address' => null,
                ];

                if ($employee) {
                    $employee->update($attributes);
                } else {
                    $employee = Employee::query()->create($attributes);
                }

                $employee->salaryStructure()->updateOrCreate(
                    [],
                    $salaryStructure
                );
            }
        }, 3);
    }

    private function existingEmployee(array $data): ?Employee
    {
        $legacyCode = (int) substr($data['code'], 3) === 10
            ? 'EMP0010'
            : 'EMP00'.(int) substr($data['code'], 3);

        return Employee::query()
            ->with('account')
            ->where('employee_code', $data['code'])
            ->orWhere('employee_code', $legacyCode)
            ->orWhere('email', $data['legacy_email'])
            ->first();
    }

    private function activeMasterIds(
        $query,
        Collection $names
    ): Collection {
        $names = $names->unique()->values();
        $ids = $query
            ->whereIn('name', $names)
            ->where('status', true)
            ->pluck('id', 'name');
        $missing = $names->reject(fn (string $name): bool => $ids->has($name));

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Employee master data is missing or inactive: '
                .$missing->implode(', ')
                .'. Run employee type, department, and designation seeders first.'
            );
        }

        return $ids;
    }

    private function employeeAccountSemanticCode(string $code): string
    {
        return 'seed_employee_'.strtolower($code);
    }

    private function employees(): array
    {
        return [
            [
                'code' => 'EMP000001',
                'legacy_email' => 'employee1@example.com',
                'name' => 'Md. Rakib Hasan',
                'email' => 'rakib.hasan@example.test',
                'employee_type' => 'Cashier',
                'department' => 'Finance Department',
                'designation' => 'Accountant',
                'payment_account' => 'cash_on_hand',
                'dob' => '1991-03-12',
                'gender' => 'Male',
                'blood_group' => 'B+',
                'marital_status' => 'Married',
                'religion' => 'Islam',
                'nid' => '19912691234567890',
                'mobile' => '01711001001',
                'mobile_two' => '01811001001',
                'emergency_contact_person' => 'Shamima Akter',
                'emergency_contact_number' => '01911001001',
                'father_name' => 'Abdul Karim',
                'mother_name' => 'Rokeya Begum',
                'present_address' => 'Mirpur 10, Dhaka',
                'permanent_address' => 'Sadar, Cumilla',
                'joining_date' => '2021-01-10',
                'highest_education' => 'BBA in Accounting',
                'reference_name' => 'Anisur Rahman',
                'reference_phone' => '01611001001',
                'reference_address' => 'Dhanmondi, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 42000,
                    'home_rent_percent' => 40,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 3000,
                    'deductions' => 1000,
                ],
            ],
            [
                'code' => 'EMP000002',
                'legacy_email' => 'employee2@example.com',
                'name' => 'Nusrat Jahan',
                'email' => 'nusrat.jahan@example.test',
                'employee_type' => 'HR Admin',
                'department' => 'HR Department',
                'designation' => 'HR Manager',
                'payment_account' => 'bank_dutch_bangla',
                'dob' => '1990-08-24',
                'gender' => 'Female',
                'blood_group' => 'A+',
                'marital_status' => 'Married',
                'religion' => 'Islam',
                'nid' => '19902694567890123',
                'mobile' => '01711001002',
                'mobile_two' => '01811001002',
                'emergency_contact_person' => 'Mahmudul Karim',
                'emergency_contact_number' => '01911001002',
                'father_name' => 'Nurul Islam',
                'mother_name' => 'Nasima Begum',
                'present_address' => 'Uttara, Dhaka',
                'permanent_address' => 'Kotwali, Rangpur',
                'joining_date' => '2020-06-15',
                'highest_education' => 'MBA in Human Resource Management',
                'reference_name' => 'Tahmina Sultana',
                'reference_phone' => '01611001002',
                'reference_address' => 'Banani, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 60000,
                    'home_rent_percent' => 45,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 5000,
                    'deductions' => 1500,
                ],
            ],
            [
                'code' => 'EMP000003',
                'legacy_email' => 'employee3@example.com',
                'name' => 'Tanvir Ahmed',
                'email' => 'tanvir.ahmed@example.test',
                'employee_type' => 'Manager',
                'department' => 'IT Department',
                'designation' => 'Senior Software Engineer',
                'payment_account' => 'bank_exaim',
                'dob' => '1993-11-05',
                'gender' => 'Male',
                'blood_group' => 'O+',
                'marital_status' => 'Single',
                'religion' => 'Islam',
                'nid' => '19932697890123456',
                'mobile' => '01711001003',
                'mobile_two' => '01811001003',
                'emergency_contact_person' => 'Taslima Ahmed',
                'emergency_contact_number' => '01911001003',
                'father_name' => 'Moinuddin Ahmed',
                'mother_name' => 'Taslima Begum',
                'present_address' => 'Bashundhara, Dhaka',
                'permanent_address' => 'Pahartali, Chattogram',
                'joining_date' => '2022-02-01',
                'highest_education' => 'BSc in Computer Science and Engineering',
                'reference_name' => 'Rezaul Haque',
                'reference_phone' => '01611001003',
                'reference_address' => 'Mohakhali, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 75000,
                    'home_rent_percent' => 50,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 8000,
                    'deductions' => 2000,
                ],
            ],
            [
                'code' => 'EMP000004',
                'legacy_email' => 'employee4@example.com',
                'name' => 'Farzana Akter',
                'email' => 'farzana.akter@example.test',
                'employee_type' => 'HR Admin',
                'department' => 'Admin Department',
                'designation' => 'System Administrator',
                'payment_account' => 'mobile_bank_bkash',
                'dob' => '1995-01-19',
                'gender' => 'Female',
                'blood_group' => 'AB+',
                'marital_status' => 'Single',
                'religion' => 'Islam',
                'nid' => '19952690123456789',
                'mobile' => '01711001004',
                'mobile_two' => '01811001004',
                'emergency_contact_person' => 'Monir Hossain',
                'emergency_contact_number' => '01911001004',
                'father_name' => 'Fazlul Haque',
                'mother_name' => 'Parvin Akter',
                'present_address' => 'Badda, Dhaka',
                'permanent_address' => 'Sadar, Noakhali',
                'joining_date' => '2023-04-12',
                'highest_education' => 'MSc in Information Technology',
                'reference_name' => 'Shahriar Kabir',
                'reference_phone' => '01611001004',
                'reference_address' => 'Gulshan, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 48000,
                    'home_rent_percent' => 40,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 3500,
                    'deductions' => 800,
                ],
            ],
            [
                'code' => 'EMP000005',
                'legacy_email' => 'employee5@example.com',
                'name' => 'Mahmudul Hasan',
                'email' => 'mahmudul.hasan@example.test',
                'employee_type' => 'Manager',
                'department' => 'Operations Department',
                'designation' => 'Team Lead',
                'payment_account' => 'bank_dutch_bangla',
                'dob' => '1989-06-30',
                'gender' => 'Male',
                'blood_group' => 'A-',
                'marital_status' => 'Married',
                'religion' => 'Islam',
                'nid' => '19892693456789012',
                'mobile' => '01711001005',
                'mobile_two' => '01811001005',
                'emergency_contact_person' => 'Jannat Ara',
                'emergency_contact_number' => '01911001005',
                'father_name' => 'Habibur Rahman',
                'mother_name' => 'Jahanara Begum',
                'present_address' => 'Mohammadpur, Dhaka',
                'permanent_address' => 'Sadar, Bogura',
                'joining_date' => '2019-09-01',
                'highest_education' => 'MBA in Operations Management',
                'reference_name' => 'Mizanur Rahman',
                'reference_phone' => '01611001005',
                'reference_address' => 'Farmgate, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 68000,
                    'home_rent_percent' => 45,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 6500,
                    'deductions' => 1800,
                ],
            ],
            [
                'code' => 'EMP000006',
                'legacy_email' => 'employee6@example.com',
                'name' => 'Sabiha Rahman',
                'email' => 'sabiha.rahman@example.test',
                'employee_type' => 'Manager',
                'department' => 'Marketing Department',
                'designation' => 'Marketing Executive',
                'payment_account' => 'bank_exaim',
                'dob' => '1994-09-16',
                'gender' => 'Female',
                'blood_group' => 'B-',
                'marital_status' => 'Married',
                'religion' => 'Islam',
                'nid' => '19942696789012345',
                'mobile' => '01711001006',
                'mobile_two' => '01811001006',
                'emergency_contact_person' => 'Asif Rahman',
                'emergency_contact_number' => '01911001006',
                'father_name' => 'Saidur Rahman',
                'mother_name' => 'Salma Khatun',
                'present_address' => 'Khilgaon, Dhaka',
                'permanent_address' => 'Sadar, Mymensingh',
                'joining_date' => '2022-07-18',
                'highest_education' => 'BBA in Marketing',
                'reference_name' => 'Nabila Islam',
                'reference_phone' => '01611001006',
                'reference_address' => 'Rampura, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 45000,
                    'home_rent_percent' => 40,
                    'medical_percent' => 10,
                    'conveyance_percent' => 8,
                    'other_allowances' => 4000,
                    'deductions' => 700,
                ],
            ],
            [
                'code' => 'EMP000007',
                'legacy_email' => 'employee7@example.com',
                'name' => 'Imran Hossain',
                'email' => 'imran.hossain@example.test',
                'employee_type' => 'Sales Executive',
                'department' => 'Sales Department',
                'designation' => 'Marketing Executive',
                'payment_account' => 'mobile_bank_bkash',
                'dob' => '1996-04-08',
                'gender' => 'Male',
                'blood_group' => 'O-',
                'marital_status' => 'Single',
                'religion' => 'Islam',
                'nid' => '19962692345678901',
                'mobile' => '01711001007',
                'mobile_two' => '01811001007',
                'emergency_contact_person' => 'Rashed Hossain',
                'emergency_contact_number' => '01911001007',
                'father_name' => 'Abdul Mannan',
                'mother_name' => 'Rashida Begum',
                'present_address' => 'Jatrabari, Dhaka',
                'permanent_address' => 'Sadar, Barishal',
                'joining_date' => '2024-01-07',
                'highest_education' => 'BBA',
                'reference_name' => 'Kamrul Hasan',
                'reference_phone' => '01611001007',
                'reference_address' => 'Motijheel, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 32000,
                    'home_rent_percent' => 35,
                    'medical_percent' => 10,
                    'conveyance_percent' => 10,
                    'other_allowances' => 2500,
                    'deductions' => 500,
                ],
            ],
            [
                'code' => 'EMP000008',
                'legacy_email' => 'employee8@example.com',
                'name' => 'Sharmin Sultana',
                'email' => 'sharmin.sultana@example.test',
                'employee_type' => 'Support Staff',
                'department' => 'Customer Service',
                'designation' => 'Quality Assurance',
                'payment_account' => 'cash_on_hand',
                'dob' => '1997-12-02',
                'gender' => 'Female',
                'blood_group' => 'A+',
                'marital_status' => 'Single',
                'religion' => 'Islam',
                'nid' => '19972695678901234',
                'mobile' => '01711001008',
                'mobile_two' => '01811001008',
                'emergency_contact_person' => 'Shafiqur Rahman',
                'emergency_contact_number' => '01911001008',
                'father_name' => 'Shafiqur Rahman',
                'mother_name' => 'Shahana Begum',
                'present_address' => 'Shewrapara, Dhaka',
                'permanent_address' => 'Sadar, Pabna',
                'joining_date' => '2024-03-03',
                'highest_education' => 'BA in English',
                'reference_name' => 'Maliha Khan',
                'reference_phone' => '01611001008',
                'reference_address' => 'Mirpur, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 30000,
                    'home_rent_percent' => 35,
                    'medical_percent' => 10,
                    'conveyance_percent' => 8,
                    'other_allowances' => 2000,
                    'deductions' => 400,
                ],
            ],
            [
                'code' => 'EMP000009',
                'legacy_email' => 'employee9@example.com',
                'name' => 'Arif Chowdhury',
                'email' => 'arif.chowdhury@example.test',
                'employee_type' => 'Manager',
                'department' => 'IT Department',
                'designation' => 'Software Engineer',
                'payment_account' => 'bank_dutch_bangla',
                'dob' => '1995-07-27',
                'gender' => 'Male',
                'blood_group' => 'B+',
                'marital_status' => 'Single',
                'religion' => 'Islam',
                'nid' => '19952698901234567',
                'mobile' => '01711001009',
                'mobile_two' => '01811001009',
                'emergency_contact_person' => 'Sadia Chowdhury',
                'emergency_contact_number' => '01911001009',
                'father_name' => 'Anwar Chowdhury',
                'mother_name' => 'Selina Akter',
                'present_address' => 'Tejgaon, Dhaka',
                'permanent_address' => 'Beanibazar, Sylhet',
                'joining_date' => '2023-08-20',
                'highest_education' => 'BSc in Software Engineering',
                'reference_name' => 'Nazmul Huda',
                'reference_phone' => '01611001009',
                'reference_address' => 'Karwan Bazar, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 52000,
                    'home_rent_percent' => 40,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 4500,
                    'deductions' => 1000,
                ],
            ],
            [
                'code' => 'EMP000010',
                'legacy_email' => 'employee10@example.com',
                'name' => 'Jannatul Ferdous',
                'email' => 'jannatul.ferdous@example.test',
                'employee_type' => 'Cashier',
                'department' => 'Finance Department',
                'designation' => 'Accountant',
                'payment_account' => 'bank_exaim',
                'dob' => '1992-10-14',
                'gender' => 'Female',
                'blood_group' => 'AB-',
                'marital_status' => 'Married',
                'religion' => 'Islam',
                'nid' => '19922691234567098',
                'mobile' => '01711001010',
                'mobile_two' => '01811001010',
                'emergency_contact_person' => 'Saiful Islam',
                'emergency_contact_number' => '01911001010',
                'father_name' => 'Jalal Uddin',
                'mother_name' => 'Momtaz Begum',
                'present_address' => 'Shantinagar, Dhaka',
                'permanent_address' => 'Sadar, Jashore',
                'joining_date' => '2021-11-01',
                'highest_education' => 'MCom in Accounting',
                'reference_name' => 'Farid Uddin',
                'reference_phone' => '01611001010',
                'reference_address' => 'Paltan, Dhaka',
                'salary_structure' => [
                    'basic_salary' => 50000,
                    'home_rent_percent' => 40,
                    'medical_percent' => 10,
                    'conveyance_percent' => 5,
                    'other_allowances' => 4000,
                    'deductions' => 1200,
                ],
            ],
        ];
    }
}
