<?php

namespace Database\Seeders;

use App\Models\PaymentSubType;
use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;

class PaymentSubTypeSeeder extends Seeder
{
    public function run(): void
    {
        $employeeCategory = VoucherCategory::where('name', 'Employee')->first();
        $supplierCategory = VoucherCategory::where('name', 'Supplier')->first();
        $customerCategory = VoucherCategory::where('name', 'Customer')->first();
        $officeCategory = VoucherCategory::where('name', 'Office')->first();
        $generalCategory = VoucherCategory::where('name', 'General')->first();
        $liabilityCategory = VoucherCategory::where('name', 'Liability')->first();

        $paymentSubTypes = [
            // Employee Sub-types
            ['code' => '1001', 'name' => 'Monthly Salary', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1002', 'name' => 'Salary Advance', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1003', 'name' => 'Personal Loan', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1004', 'name' => 'Bonus', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1005', 'name' => 'Overtime Payment', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1006', 'name' => 'Medical Allowance', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1007', 'name' => 'Travel Allowance', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1008', 'name' => 'Advance Return', 'voucher_category_id' => $employeeCategory->id, 'type' => 'receipt'],
            ['code' => '1009', 'name' => 'Loan Recovery', 'voucher_category_id' => $employeeCategory->id, 'type' => 'receipt'],
            ['code' => '1010', 'name' => 'Salary Deduction', 'voucher_category_id' => $employeeCategory->id, 'type' => 'receipt'],
            ['code' => '1011', 'name' => 'Training Cost', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1012', 'name' => 'Commission Payment', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1013', 'name' => 'Incentive Payment', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1014', 'name' => 'Salary & Allowances', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1015', 'name' => 'Uniform & Leaveries', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],
            ['code' => '1016', 'name' => 'Labour Charges & Wages', 'voucher_category_id' => $employeeCategory->id, 'type' => 'payment'],

            // Supplier Sub-types
            ['code' => '1017', 'name' => 'Cash Purchase', 'voucher_category_id' => $supplierCategory->id, 'type' => 'payment'],
            ['code' => '1018', 'name' => 'Credit Payment', 'voucher_category_id' => $supplierCategory->id, 'type' => 'payment'],
            ['code' => '1019', 'name' => 'Advance Payment', 'voucher_category_id' => $supplierCategory->id, 'type' => 'payment'],
            ['code' => '1020', 'name' => 'Security Deposit', 'voucher_category_id' => $supplierCategory->id, 'type' => 'payment'],
            ['code' => '1021', 'name' => 'Transportation Cost', 'voucher_category_id' => $supplierCategory->id, 'type' => 'payment'],
            ['code' => '1022', 'name' => 'Refund Received', 'voucher_category_id' => $supplierCategory->id, 'type' => 'receipt'],
            ['code' => '1023', 'name' => 'Discount Received', 'voucher_category_id' => $supplierCategory->id, 'type' => 'receipt'],

            // Customer Sub-types
            ['code' => '1024', 'name' => 'Cash Sale', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1025', 'name' => 'Credit Payment', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1026', 'name' => 'Advance Received', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1027', 'name' => 'Security Deposit', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1028', 'name' => 'Refund Given', 'voucher_category_id' => $customerCategory->id, 'type' => 'payment'],
            ['code' => '1029', 'name' => 'Discount Given', 'voucher_category_id' => $customerCategory->id, 'type' => 'payment'],
            ['code' => '1030', 'name' => 'Late Payment Fee', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1031', 'name' => 'Service Charge', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],
            ['code' => '1032', 'name' => 'Delivery Charge', 'voucher_category_id' => $customerCategory->id, 'type' => 'receipt'],

            // Office Sub-types
            ['code' => '1033', 'name' => 'Utility Bills', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1034', 'name' => 'Rent Payment', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1035', 'name' => 'Internet/Phone Bill', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1036', 'name' => 'Office Supplies', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1037', 'name' => 'Equipment Purchase', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1038', 'name' => 'Maintenance Cost', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1039', 'name' => 'Insurance Premium', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1040', 'name' => 'Tax Payment', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1041', 'name' => 'Bank Charges', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1042', 'name' => 'Rent Income', 'voucher_category_id' => $officeCategory->id, 'type' => 'receipt'],
            ['code' => '1043', 'name' => 'Interest Income', 'voucher_category_id' => $officeCategory->id, 'type' => 'receipt'],
            ['code' => '1044', 'name' => 'Service Income', 'voucher_category_id' => $officeCategory->id, 'type' => 'receipt'],
            ['code' => '1045', 'name' => 'Petty Cash', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1046', 'name' => 'Fuel Cost', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1047', 'name' => 'Marketing Expense', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1048', 'name' => 'Repair & Maintenance', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1049', 'name' => 'Legal Fees', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1050', 'name' => 'Audit Fees', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1051', 'name' => 'Mobile Bill', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1052', 'name' => 'Postage, Courier & Stamps', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1053', 'name' => 'Photocopy, Printing & Stationary', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1054', 'name' => 'Printing', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1055', 'name' => 'Travelling & Conveyance', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1056', 'name' => 'Fuel Bill for Generator', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1057', 'name' => 'Fuel Bill for Lory', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1058', 'name' => 'Entertainment', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1059', 'name' => 'Newspaper & Periodicals', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1060', 'name' => 'Electricity Bill', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1061', 'name' => 'Fooding & Iftar', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1062', 'name' => 'Office Maintenance', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1063', 'name' => 'Gardening Expenses', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1064', 'name' => 'Washing & Cleaning', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1065', 'name' => 'Audit & Professional Fees', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1066', 'name' => 'Registration & Renewals', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1067', 'name' => 'Business Promotion', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1068', 'name' => 'IT Accessories', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1069', 'name' => 'Loading & Un-Loading Expenses', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],
            ['code' => '1070', 'name' => 'Depreciation & Amortization', 'voucher_category_id' => $officeCategory->id, 'type' => 'payment'],

            // General Sub-types
            ['code' => '1071', 'name' => 'Account Transfer', 'voucher_category_id' => $generalCategory->id, 'type' => 'both'],
            ['code' => '1072', 'name' => 'Opening Balance', 'voucher_category_id' => $generalCategory->id, 'type' => 'both'],
            ['code' => '1073', 'name' => 'Adjustment', 'voucher_category_id' => $generalCategory->id, 'type' => 'both'],

            // Liability Sub-types
            ['code' => '1074', 'name' => 'Loan Payment', 'voucher_category_id' => $liabilityCategory->id, 'type' => 'payment'],
            ['code' => '1075', 'name' => 'Interest Payment', 'voucher_category_id' => $liabilityCategory->id, 'type' => 'payment'],
            ['code' => '1076', 'name' => 'Loan Received', 'voucher_category_id' => $liabilityCategory->id, 'type' => 'receipt'],
            ['code' => '1077', 'name' => 'Liability Settlement', 'voucher_category_id' => $liabilityCategory->id, 'type' => 'payment']
        ];

        foreach ($paymentSubTypes as $subType) {
            PaymentSubType::create($subType);
        }
    }
}
