<?php

namespace Database\Seeders;

use App\Helpers\AccountGroupHelper;
use App\Helpers\AccountHelper;
use App\Models\Account;
use App\Models\Group;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use RuntimeException;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = Group::query()
            ->where('code', AccountGroupHelper::code('account_payable'))
            ->value('id');

        if (! $groupId) {
            throw new RuntimeException(
                'Supplier payable group is missing. Run GroupSeeder before SupplierSeeder.'
            );
        }

        $suppliers = [
            ['name' => 'ABC Traders', 'mobile' => '01711111111', 'email' => 'abc@example.com', 'address' => 'Dhaka'],
            ['name' => 'XYZ Suppliers', 'mobile' => '01722222222', 'email' => 'xyz@example.com', 'address' => 'Chittagong'],
            ['name' => 'Global Imports', 'mobile' => '01733333333', 'email' => 'global@example.com', 'address' => 'Sylhet'],
        ];

        foreach ($suppliers as $supplierData) {
            $account = Account::query()->create([
                'group_id' => $groupId,
                'name' => $supplierData['name'],
                'ac_number' => AccountHelper::generateAccountNumber(),
                'currency' => 'BDT',
                'is_control_account' => false,
                'allow_manual_posting' => true,
                'is_system' => false,
                'status' => true,
            ]);

            Supplier::create([
                'account_id' => $account->id,
                'name' => $supplierData['name'],
                'mobile' => $supplierData['mobile'],
                'email' => $supplierData['email'],
                'address' => $supplierData['address'],
                'status' => true,
            ]);
        }
    }
}
