<?php

namespace Database\Seeders;

use App\Helpers\AccountGroupHelper;
use App\Helpers\AccountHelper;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Group;
use Illuminate\Database\Seeder;
use RuntimeException;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = Group::query()
            ->where(
                'code',
                AccountGroupHelper::code('employee_management')
            )
            ->value('id');

        if (! $groupId) {
            throw new RuntimeException(
                'Employee account group is missing. Run GroupSeeder before EmployeeSeeder.'
            );
        }

        for ($i = 1; $i <= 10; $i++) {
            $account = Account::query()->create([
                'group_id' => $groupId,
                'name' => "Employee $i",
                'ac_number' => AccountHelper::generateAccountNumber(),
                'currency' => 'BDT',
                'is_control_account' => false,
                'allow_manual_posting' => true,
                'is_system' => false,
                'status' => true,
            ]);

            Employee::create([
                'account_id' => $account->id,
                'emp_type_id' => 1,
                'department_id' => 1,
                'designation_id' => 1,
                'employee_code' => "EMP00$i",
                'employee_name' => "Employee $i",
                'email' => "employee$i@example.com",
                'order' => $i,
                'dob' => '1990-01-01',
                'gender' => $i % 2 == 0 ? 'Male' : 'Female',
                'blood_group' => 'A+',
                'marital_status' => 'Single',
                'emergency_contact_person' => "Emergency Contact $i",
                'religion' => 'Islam',
                'nid' => "123456789$i",
                'mobile' => "01700000$i",
                'mobile_two' => "01800000$i",
                'emergency_contact_number' => "01900000$i",
                'father_name' => "Father $i",
                'mother_name' => "Mother $i",
                'present_address' => "Present Address $i",
                'permanent_address' => "Permanent Address $i",
                'job_status' => 'Active',
                'joining_date' => '2024-01-01',
                'status' => true,
                'status_date' => '2024-01-01',
                'photo' => "photo$i.jpg",
                'signature' => "signature$i.jpg",
                'highest_education' => 'Bachelor',
                'reference_one_name' => "Reference One $i",
                'reference_one_phone' => "01600000$i",
                'reference_one_address' => "Reference One Address $i",
                'reference_two_name' => "Reference Two $i",
                'reference_two_phone' => "01500000$i",
                'reference_two_address' => "Reference Two Address $i",
            ]);
        }
    }
}
