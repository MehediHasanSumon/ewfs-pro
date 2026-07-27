<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmpType;

class EmpTypeSeeder extends Seeder
{
    public function run(): void
    {
        $empTypes = [
            ['name' => 'Director', 'status' => true],
            ['name' => 'Manager', 'status' => true],
            ['name' => 'HR Admin', 'status' => true],
            ['name' => 'Casher', 'status' => true],
            ['name' => 'Sells Man', 'status' => true],
            ['name' => 'Driver', 'status' => true],
            ['name' => 'Helper', 'status' => true],
            ['name' => 'Clenar', 'status' => false]
        ];

        foreach ($empTypes as $empType) {
            EmpType::create($empType);
        }
    }
}