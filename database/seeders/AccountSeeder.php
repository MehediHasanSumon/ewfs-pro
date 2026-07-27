<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Helpers\AccountHelper;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Office Cash',
                'group_code' => '100020002',
                'group_id' => 13,
            ],
            [
                'name' => 'bKash : 01755-683388',
                'group_code' => '100020003',
                'group_id' => 14,
            ],
            [
                'name' => 'Exaim Bank PLC',
                'group_code' => '100020004',
                'group_id' => 15,
            ],
            [
                'name' => 'Dutch Bangla Bank A/C: 123456',
                'group_code' => '100020004',
                'group_id' => 15,
            ],
        ];

        foreach ($accounts as $accountData) {
            Account::create([
                'name' => $accountData['name'],
                'ac_number' => AccountHelper::generateAccountNumber(),
                'group_id' => $accountData['group_id'],
                'group_code' => $accountData['group_code'],
                'due_amount' => 0,
                'paid_amount' => 0,
                'total_amount' => 0,
                'status' => true,
            ]);
        }
    }
}
