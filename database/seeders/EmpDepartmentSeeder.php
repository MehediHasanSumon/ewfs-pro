<?php

namespace Database\Seeders;

use App\Models\EmpDepartment;
use App\Models\EmpType;
use Illuminate\Database\Seeder;
use RuntimeException;

class EmpDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $types = EmpType::query()
            ->where('status', true)
            ->pluck('id', 'name');

        foreach ($this->departments() as $definition) {
            $typeId = $types->get($definition['employee_type']);

            if (! $typeId) {
                throw new RuntimeException(
                    "Employee type [{$definition['employee_type']}] is missing. Run EmpTypeSeeder first."
                );
            }

            $department = EmpDepartment::query()
                ->where('code', $definition['code'])
                ->orWhere('name', $definition['name'])
                ->first() ?? new EmpDepartment;

            $department->fill([
                'emp_type_id' => $typeId,
                'code' => $definition['code'],
                'name' => $definition['name'],
                'status' => true,
            ])->save();
        }
    }

    private function departments(): array
    {
        return [
            ['code' => 'IT', 'name' => 'IT Department', 'employee_type' => 'Manager'],
            ['code' => 'HR', 'name' => 'HR Department', 'employee_type' => 'HR Admin'],
            ['code' => 'FINANCE', 'name' => 'Finance Department', 'employee_type' => 'Cashier'],
            ['code' => 'MARKETING', 'name' => 'Marketing Department', 'employee_type' => 'Manager'],
            ['code' => 'SALES', 'name' => 'Sales Department', 'employee_type' => 'Sales Executive'],
            ['code' => 'OPERATIONS', 'name' => 'Operations Department', 'employee_type' => 'Manager'],
            ['code' => 'ADMIN', 'name' => 'Admin Department', 'employee_type' => 'HR Admin'],
            ['code' => 'CUSTOMER-SERVICE', 'name' => 'Customer Service', 'employee_type' => 'Support Staff'],
        ];
    }
}
