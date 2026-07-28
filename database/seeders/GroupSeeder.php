<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        Group::query()->delete();

        $groups = [
            [1, null, '1', 'Assets', 'asset', 'debit'],
            [2, null, '2', 'Expenses', 'expense', 'debit'],
            [3, null, '3', 'Income', 'revenue', 'credit'],
            [4, null, '4', 'Liabilities', 'liability', 'credit'],
            [5, 1, '10001', 'Fixed Asset', 'asset', 'debit'],
            [6, 1, '10002', 'Current Asset', 'asset', 'debit'],
            [7, 6, '100020001', 'Account Receivable', 'asset', 'debit'],
            [9, 5, '100010001', 'Land', 'asset', 'debit'],
            [10, 4, '40001', 'Current Liabilities', 'liability', 'credit'],
            [11, 10, '400010001', 'Account Payable', 'liability', 'credit'],
            [12, 10, '400010002', 'Bank Loan', 'liability', 'credit'],
            [13, 6, '100020002', 'Cash in hand', 'asset', 'debit'],
            [14, 6, '100020003', 'Mobile Bank', 'asset', 'debit'],
            [15, 6, '100020004', 'Bank Account', 'asset', 'debit'],
            [16, 4, '40002', 'Employee Management', 'liability', 'credit'],
        ];

        foreach ($groups as $group) {
            Group::create([
                'id' => $group[0],
                'parent_id' => $group[1],
                'code' => $group[2],
                'name' => $group[3],
                'account_class' => $group[4],
                'normal_balance' => $group[5],
                'is_system' => true,
                'status' => true,
            ]);
        }
    }
}
