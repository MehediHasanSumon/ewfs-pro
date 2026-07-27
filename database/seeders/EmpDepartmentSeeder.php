<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmpDepartment;

class EmpDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['emp_type_id' => 1, 'name' => 'IT Department', 'status' => true],
            ['emp_type_id' => 2, 'name' => 'HR Department', 'status' => true],
            ['emp_type_id' => 3, 'name' => 'Finance Department', 'status' => true],
            ['emp_type_id' => 4, 'name' => 'Marketing Department', 'status' => true],
            ['emp_type_id' => 5, 'name' => 'Sales Department', 'status' => true],
            ['emp_type_id' => 1, 'name' => 'Operations Department', 'status' => true],
            ['emp_type_id' => 2, 'name' => 'Admin Department', 'status' => true],
            ['emp_type_id' => 3, 'name' => 'Customer Service', 'status' => true]
        ];

        foreach ($departments as $department) {
            EmpDepartment::create($department);
        }
    }
}