<?php

namespace Database\Seeders;

use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OperatingVoucherTransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $category = VoucherCategory::query()
                ->where('code', 'VC004')
                ->orWhere('name', 'Operating')
                ->first();

            if (! $category) {
                throw new RuntimeException('Operating voucher category (VC004) not found.');
            }

            $types = [
                // Payment Types
                [
                    'code' => '2002',
                    'name' => 'Mobile Bill',
                    'voucher_type' => 'payment',
                    'description' => 'Mobile phone and communication expenses.',
                    'sort_order' => 2,
                    'is_system' => false,
                ],
                [
                    'code' => '2003',
                    'name' => 'Postage, Courier & Stamps',
                    'voucher_type' => 'payment',
                    'description' => 'Postage, parcel, courier and stamp charges.',
                    'sort_order' => 3,
                    'is_system' => false,
                ],
                [
                    'code' => '2004',
                    'name' => 'Photocopy, Printing & Stationary',
                    'voucher_type' => 'payment',
                    'description' => 'Photocopy, stationery and office paper supply expenses.',
                    'sort_order' => 4,
                    'is_system' => false,
                ],
                [
                    'code' => '2005',
                    'name' => 'Printing',
                    'voucher_type' => 'payment',
                    'description' => 'Official document and media printing expenses.',
                    'sort_order' => 5,
                    'is_system' => false,
                ],
                [
                    'code' => '2006',
                    'name' => 'Travelling & Conveyance',
                    'voucher_type' => 'payment',
                    'description' => 'Staff local travel, transport and conveyance costs.',
                    'sort_order' => 6,
                    'is_system' => false,
                ],
                [
                    'code' => '2007',
                    'name' => 'Fuel Bill for Generator',
                    'voucher_type' => 'payment',
                    'description' => 'Generator fuel and operation charges.',
                    'sort_order' => 7,
                    'is_system' => false,
                ],
                [
                    'code' => '2008',
                    'name' => 'Fuel Bill for Lory',
                    'voucher_type' => 'payment',
                    'description' => 'Lorry and heavy transport vehicle fuel expenses.',
                    'sort_order' => 8,
                    'is_system' => false,
                ],
                [
                    'code' => '2009',
                    'name' => 'Entertainment',
                    'voucher_type' => 'payment',
                    'description' => 'Guest tea, snacks and client hospitality expenses.',
                    'sort_order' => 9,
                    'is_system' => false,
                ],
                [
                    'code' => '2010',
                    'name' => 'Newspaper & Periodicals',
                    'voucher_type' => 'payment',
                    'description' => 'Daily newspapers, magazines and journal subscriptions.',
                    'sort_order' => 10,
                    'is_system' => false,
                ],
                [
                    'code' => '2011',
                    'name' => 'Electricity Bill',
                    'voucher_type' => 'payment',
                    'description' => 'Utility electric power charges.',
                    'sort_order' => 11,
                    'is_system' => false,
                ],
                [
                    'code' => '2012',
                    'name' => 'Fooding & Iftar',
                    'voucher_type' => 'payment',
                    'description' => 'Employee fooding, meal and iftar party costs.',
                    'sort_order' => 12,
                    'is_system' => false,
                ],
                [
                    'code' => '2013',
                    'name' => 'Office Maintenance',
                    'voucher_type' => 'payment',
                    'description' => 'General office upkeep and facility maintenance.',
                    'sort_order' => 13,
                    'is_system' => false,
                ],
                [
                    'code' => '2014',
                    'name' => 'Uniform & Leaveries',
                    'voucher_type' => 'payment',
                    'description' => 'Staff uniforms, dresses and safety gear expenses.',
                    'sort_order' => 14,
                    'is_system' => false,
                ],
                [
                    'code' => '2015',
                    'name' => 'Gardening Expenses',
                    'voucher_type' => 'payment',
                    'description' => 'Premises garden maintenance and landscaping.',
                    'sort_order' => 15,
                    'is_system' => false,
                ],
                [
                    'code' => '2016',
                    'name' => 'Washing & Cleaning',
                    'voucher_type' => 'payment',
                    'description' => 'Office washing, hygiene and cleaning materials.',
                    'sort_order' => 16,
                    'is_system' => false,
                ],
                [
                    'code' => '2017',
                    'name' => 'Audit & Professional Fees',
                    'voucher_type' => 'payment',
                    'description' => 'External audit, legal and professional consultant fees.',
                    'sort_order' => 17,
                    'is_system' => false,
                ],
                [
                    'code' => '2018',
                    'name' => 'Repair & Maintenance',
                    'voucher_type' => 'payment',
                    'description' => 'Machinery, equipment and asset repairs.',
                    'sort_order' => 18,
                    'is_system' => false,
                ],
                [
                    'code' => '2019',
                    'name' => 'Registration & Renewals',
                    'voucher_type' => 'payment',
                    'description' => 'Trade license, government registration and renewal fees.',
                    'sort_order' => 19,
                    'is_system' => false,
                ],
                [
                    'code' => '2020',
                    'name' => 'Labour Charges & Wages',
                    'voucher_type' => 'payment',
                    'description' => 'Daily labour charges, handling and wage expenses.',
                    'sort_order' => 20,
                    'is_system' => false,
                ],
                [
                    'code' => '2021',
                    'name' => 'Business Promotion',
                    'voucher_type' => 'payment',
                    'description' => 'Marketing, advertisement and promotional expenses.',
                    'sort_order' => 21,
                    'is_system' => false,
                ],
                [
                    'code' => '2022',
                    'name' => 'IT Accessoriees',
                    'voucher_type' => 'payment',
                    'description' => 'Computer, internet, cables and IT accessory expenses.',
                    'sort_order' => 22,
                    'is_system' => false,
                ],
                [
                    'code' => '2023',
                    'name' => 'Loading & Un-Loading Expenses',
                    'voucher_type' => 'payment',
                    'description' => 'Product loading, unloading and carriage labor charges.',
                    'sort_order' => 23,
                    'is_system' => false,
                ],
                [
                    'code' => '2024',
                    'name' => 'Depreciation & Amortization',
                    'voucher_type' => 'payment',
                    'description' => 'Periodic depreciation and asset amortization.',
                    'sort_order' => 24,
                    'is_system' => false,
                ],
                [
                    'code' => '2025',
                    'name' => 'Bank Charge',
                    'voucher_type' => 'payment',
                    'description' => 'Bank service fees, excise duty and commission charges.',
                    'sort_order' => 25,
                    'is_system' => false,
                ],

                // Receipt Types
                [
                    'code' => '2051',
                    'name' => 'Scrap & Wastage Sale',
                    'voucher_type' => 'receipt',
                    'description' => 'Receipt from selling discarded scrap, drums and materials.',
                    'sort_order' => 51,
                    'is_system' => false,
                ],
                [
                    'code' => '2052',
                    'name' => 'Discount & Commission Received',
                    'voucher_type' => 'receipt',
                    'description' => 'Commission or promotional discount income received.',
                    'sort_order' => 52,
                    'is_system' => false,
                ],
                [
                    'code' => '2053',
                    'name' => 'Interest & Profit Received',
                    'voucher_type' => 'receipt',
                    'description' => 'Bank deposit interest or financial earnings received.',
                    'sort_order' => 53,
                    'is_system' => false,
                ],
                [
                    'code' => '2054',
                    'name' => 'Rental Income',
                    'voucher_type' => 'receipt',
                    'description' => 'Rent received from sub-leased property or spaces.',
                    'sort_order' => 54,
                    'is_system' => false,
                ],
                [
                    'code' => '2055',
                    'name' => 'Other Operating Income',
                    'voucher_type' => 'receipt',
                    'description' => 'Miscellaneous operational receipts and collections.',
                    'sort_order' => 55,
                    'is_system' => false,
                ],
            ];

            foreach ($types as $typeData) {
                VoucherTransactionType::query()->updateOrCreate(
                    [
                        'voucher_category_id' => $category->id,
                        'name' => $typeData['name'],
                    ],
                    [
                        'code' => $typeData['code'],
                        'voucher_type' => $typeData['voucher_type'],
                        'description' => $typeData['description'],
                        'sort_order' => $typeData['sort_order'],
                        'status' => true,
                        'is_system' => $typeData['is_system'],
                    ]
                );
            }
        });
    }
}
