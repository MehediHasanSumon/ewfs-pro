<?php

namespace Database\Seeders;

use App\Models\EmpDesignation;
use Illuminate\Database\Seeder;

class EmpDesignationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->designations() as $definition) {
            EmpDesignation::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'code' => $definition['code'],
                    'status' => true,
                ]
            );
        }
    }

    private function designations(): array
    {
        return [
            ['code' => 'SOFTWARE-ENGINEER', 'name' => 'Software Engineer'],
            ['code' => 'SENIOR-SOFTWARE-ENGINEER', 'name' => 'Senior Software Engineer'],
            ['code' => 'TEAM-LEAD', 'name' => 'Team Lead'],
            ['code' => 'PROJECT-MANAGER', 'name' => 'Project Manager'],
            ['code' => 'HR-MANAGER', 'name' => 'HR Manager'],
            ['code' => 'ACCOUNTANT', 'name' => 'Accountant'],
            ['code' => 'MARKETING-EXECUTIVE', 'name' => 'Marketing Executive'],
            ['code' => 'SALES-REPRESENTATIVE', 'name' => 'Sales Representative'],
            ['code' => 'SYSTEM-ADMINISTRATOR', 'name' => 'System Administrator'],
            ['code' => 'QUALITY-ASSURANCE', 'name' => 'Quality Assurance'],
        ];
    }
}
