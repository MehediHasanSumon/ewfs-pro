<?php

namespace Database\Seeders;

use App\Helpers\AccountHelper;
use App\Models\Account;
use App\Models\Group;
use Illuminate\Database\Seeder;
use RuntimeException;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Office Cash',
                'group_code' => '100020002',
                'semantic_code' => 'cash_on_hand',
                'is_system' => true,
            ],
            [
                'name' => 'bKash : 01755-683388',
                'group_code' => '100020003',
                'semantic_code' => 'mobile_bank_bkash',
                'is_system' => false,
            ],
            [
                'name' => 'Exaim Bank PLC',
                'group_code' => '100020004',
                'semantic_code' => 'bank_exaim',
                'is_system' => false,
            ],
            [
                'name' => 'Dutch Bangla Bank A/C: 123456',
                'group_code' => '100020004',
                'semantic_code' => 'bank_dutch_bangla',
                'is_system' => false,
            ],
        ];

        foreach ($accounts as $accountData) {
            $groupId = Group::query()
                ->where('code', $accountData['group_code'])
                ->value('id');

            if (! $groupId) {
                throw new RuntimeException(
                    "Group {$accountData['group_code']} is missing. Run GroupSeeder before AccountSeeder."
                );
            }

            $account = Account::query()
                ->where('semantic_code', $accountData['semantic_code'])
                ->first();

            $attributes = [
                'group_id' => $groupId,
                'name' => $accountData['name'],
                'semantic_code' => $accountData['semantic_code'],
                'currency' => 'BDT',
                'is_control_account' => false,
                'allow_manual_posting' => true,
                'is_system' => $accountData['is_system'],
                'status' => true,
            ];

            if ($account) {
                $account->update($attributes);

                continue;
            }

            Account::query()->create([
                ...$attributes,
                'ac_number' => AccountHelper::generateAccountNumber(),
            ]);
        }
    }
}
