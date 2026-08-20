<?php

namespace Database\Seeders;

use App\Helpers\AccountGroupHelper;
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
            ->where(
                'code',
                AccountGroupHelper::code('account_receivable')
            )
            ->value('id');

        if (! $groupId) {
            throw new RuntimeException(
                'Customer receivable group is missing. Run GroupSeeder before CustomerSeeder.'
            );
        }

        $customersData = [
            [
                'name' => 'Aichi Hospital Ltd.',
                'proprietor_name' => 'Sir Mohammad Al-Quadir (Shuvo)',
                'mobile' => '01820580027',
                'address' => '35 & 37 Sec #8. Uttara, Dhaka',
                'email' => 'aichihospital@example.com',
            ],
            [
                'name' => 'Etafil Accessories Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429990',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'etafilaccessories@example.com',
            ],
            [
                'name' => 'EH Enterprise Ltd.',
                'proprietor_name' => 'Mr: Hafij Ullah',
                'mobile' => '01673358158',
                'address' => 'Holding: 18/8/1, Garan, Chatbari, Mirpur, Dhaka',
                'email' => 'ehenterprise@example.com',
            ],
            [
                'name' => 'East West Medical College & Hospital',
                'proprietor_name' => 'Mr. Md. Shahadat Siddiquei.',
                'mobile' => '01712966753',
                'address' => 'Aichi Nagar JBCS Saroni, Khayertek, Turag, Dhaka-1711',
                'email' => 'eastwestmedical@example.com',
            ],
            [
                'name' => 'Firoz Transport Agency',
                'proprietor_name' => 'Mr. Md. Firoz Alom.',
                'mobile' => '01675154261',
                'address' => 'Dhaka',
                'email' => 'firoztransport@example.com',
            ],
            [
                'name' => 'MD. Khalid Hossain',
                'proprietor_name' => 'Mr. Khalid Hossain.',
                'mobile' => '01900000019',
                'address' => 'Aichi Nagar JBCS Saroni, Khayertek, Turag, Dhaka-1711',
                'email' => 'khalidhossain@example.com',
            ],
            [
                'name' => 'Khan Shaheb',
                'proprietor_name' => 'Mr. Md. Shahadat Hossain.',
                'mobile' => '01712775485',
                'address' => 'Uttar Razabari, Turag , Dhaka.',
                'email' => 'khanshaheb@example.com',
            ],
            [
                'name' => 'Nerolac',
                'proprietor_name' => 'Mr. Kondoker Kamrul Islam.',
                'mobile' => '01713861696',
                'address' => 'Flat#8/A, House#15,Road#01, Sector#07, Uttara, Dhaka-1230.',
                'email' => 'nerolac@example.com',
            ],
            [
                'name' => 'Renata Limited',
                'proprietor_name' => 'Mr. Md. Robiul Hossain.',
                'mobile' => '01844097558',
                'address' => 'House – 39, Block – C, Road – 6, Dhour, Turag, Uttara, Dhaka-1230',
                'email' => 'renatalimited@example.com',
            ],
            [
                'name' => 'Shah Cement Industries Ltd.',
                'proprietor_name' => 'Mr. Md. Tajuddin',
                'mobile' => '01718827060',
                'address' => 'Gulshan - 2, Dhaka',
                'email' => 'shahcement@example.com',
            ],
            [
                'name' => 'Ship Aichi Madical Service LTD',
                'proprietor_name' => 'Mr. Alauddin Bhuyan.',
                'mobile' => '01781151316',
                'address' => 'Aichi Nagar JBCS Saroni, Khayertek, Turag, Dhaka-1711',
                'email' => 'shipaichi@example.com',
            ],
            [
                'name' => 'DFL Plastic',
                'proprietor_name' => 'Mr. Syed Mahbub Murad.',
                'mobile' => '01790026423',
                'address' => 'House #22/E, Road# 13/C, Block# E, Banani, Dhaka-1213.',
                'email' => 'dflplastic@example.com',
            ],
            [
                'name' => 'Etafil Bangladesh Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429991',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'etafilbd@example.com',
            ],
            [
                'name' => 'Tamishna Dyeing Industries Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01979147859',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'tamishnadyeing@example.com',
            ],
            [
                'name' => 'Tamishna Fashion Wear Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429994',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'tamishnafashion@example.com',
            ],
            [
                'name' => 'Tamishna Head Office.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429995',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'tamishnaheadoffice@example.com',
            ],
            [
                'name' => 'Tamishna Logistics Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429996',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'tamishnalogistics@example.com',
            ],
            [
                'name' => 'Tamishna Sythetics Ltd.',
                'proprietor_name' => 'Mr. Md. Johirul Islam',
                'mobile' => '01313429997',
                'address' => 'Badam, Tongi, Gazipur',
                'email' => 'tamishnasythetics@example.com',
            ],
        ];

        foreach ($customersData as $index => $cData) {
            $account = Account::query()->create([
                'group_id' => $groupId,
                'name' => $cData['name'],
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
                'name' => $cData['name'],
                'proprietor_name' => $cData['proprietor_name'],
                'mobile' => $cData['mobile'],
                'email' => $cData['email'],
                'nid_number' => 'NID' . str_pad($index + 1, 8, '0', STR_PAD_LEFT),
                'vat_reg_no' => 'VAT' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'tin_no' => 'TIN' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'trade_license' => 'TL' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'discount_rate' => 0.00,
                'credit_limit' => 500000.00,
                'credit_days' => 30,
                'address' => $cData['address'],
                'status' => true,
            ]);
        }
    }
}
