<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        Group::query()->delete();
        
        $groups = [
            [1, '1', 'Assets', 'ROOT', true],
            [2, '2', 'Expenses', 'ROOT', true],
            [3, '3', 'Income', 'ROOT', true],
            [4, '4', 'Liabilities', 'ROOT', true],
            [5, '10001', 'Fixed Asset', '1', true],
            [6, '10002', 'Current Asset', '1', true],
            [7, '100020001', 'Account Receivable', '10002', true],
            [9, '100010001', 'Land', '10001', true],
            [10, '40001', 'Current Liabilities', '4', true],
            [11, '400010001', 'Account Payable', '40001', true],
            [12, '400010002', 'Bank Loan', '40001', true],
            [13, '100020002', 'Cash in hand', '10002', true],
            [14, '100020003', 'Mobile Bank', '10002', true],
            [15, '100020004', 'Bank Account', '10002', true],
            [16, '40002', 'Employee Management', '4', true]
        ];

        foreach ($groups as $group) {
            Group::create([
                'id' => $group[0],
                'code' => $group[1],
                'name' => $group[2],
                'parents' => $group[3],
                'status' => $group[4],
            ]);
        }
    }
}
