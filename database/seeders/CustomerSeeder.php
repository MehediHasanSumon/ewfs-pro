<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Account;
use App\Helpers\AccountHelper;
use App\Helpers\CustomerHelper;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            // Create account first
            $account = Account::create([
                'name' => "Customer $i",
                'ac_number' => AccountHelper::generateAccountNumber(),
                'group_id' => 7,
                'group_code' => '100020001',
                'due_amount' => 0,
                'paid_amount' => 0,
                'status' => true,
            ]);

            Customer::create([
                'account_id' => $account->id,
                'code' => CustomerHelper::generateCustomerCode(),
                'name' => "Customer $i",
                'mobile' => "01800000$i",
                'email' => "customer$i@example.com",
                'nid_number' => "987654321$i",
                'vat_reg_no' => "VAT00$i",
                'tin_no' => "TIN00$i",
                'trade_license' => "TL00$i",
                'discount_rate' => 5.00,
                'security_deposit' => 1000.00,
                'credit_limit' => 10000.00,
                'address' => "Customer Address $i",
                'status' => true,
            ]);
        }
    }
}
