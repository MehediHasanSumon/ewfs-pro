<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmpDesignation;

class EmpDesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            ['name' => 'Software Engineer', 'status' => true],
            ['name' => 'Senior Software Engineer', 'status' => true],
            ['name' => 'Team Lead', 'status' => true],
            ['name' => 'Project Manager', 'status' => true],
            ['name' => 'HR Manager', 'status' => true],
            ['name' => 'Accountant', 'status' => true],
            ['name' => 'Marketing Executive', 'status' => true],
            ['name' => 'Sales Representative', 'status' => false],
            ['name' => 'System Administrator', 'status' => true],
            ['name' => 'Quality Assurance', 'status' => true]
        ];

        foreach ($designations as $designation) {
            EmpDesignation::create($designation);
        }
    }
}