<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(EmpDesignationSeeder::class);
        $this->call(EmpTypeSeeder::class);
        $this->call(EmpDepartmentSeeder::class);
        $this->call(GroupSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(EmployeeSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(VehicleSeeder::class);
        $this->call(AccountSeeder::class);
        $this->call(SystemVoucherCategorySeeder::class);
        $this->call(SystemVoucherTransactionTypeSeeder::class);
        $this->call(SMSTemplateSeeder::class);
    }
}
