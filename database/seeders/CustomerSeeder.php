<?php

namespace Database\Seeders;

use App\Helpers\AccountHelper;
use App\Helpers\CustomerHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Group;
use Illuminate\Database\Seeder;
use RuntimeException;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = Group::query()
            ->where('code', '100020001')
            ->value('id');

        if (! $groupId) {
            throw new RuntimeException(
                'Customer receivable group is missing. Run GroupSeeder before CustomerSeeder.'
            );
        }

        for ($i = 1; $i <= 10; $i++) {
            $account = Account::query()->create([
                'group_id' => $groupId,
                'name' => "Customer $i",
                'ac_number' => AccountHelper::generateAccountNumber(),
                'currency' => 'BDT',
                'is_control_account' => false,
                'allow_manual_posting' => true,
                'is_system' => false,
                'status' => true,
            ]);

            Customer::create([
                'account_id' => $account->id,
                'code' => CustomerHelper::generateCustomerCode(),
                'name' => "Customer $i",
                'proprietor_name' => "Proprietor $i",
                'mobile' => "01800000$i",
                'email' => "customer$i@example.com",
                'nid_number' => "987654321$i",
                'vat_reg_no' => "VAT00$i",
                'tin_no' => "TIN00$i",
                'trade_license' => "TL00$i",
                'discount_rate' => 5.00,
                'credit_limit' => 10000.00,
                'address' => "Customer Address $i",
                'status' => true,
            ]);
        }
    }
}
