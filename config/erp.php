<?php

$productCategoryCodes = [
    'oil' => 1001,
    'gas' => 1002,
    'lubricant' => 1003,
];

$accountGroups = [
    'assets' => [
        'code' => '1',
        'name' => 'Assets',
        'parent' => null,
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'expenses' => [
        'code' => '2',
        'name' => 'Expenses',
        'parent' => null,
        'account_class' => 'expense',
        'normal_balance' => 'debit',
    ],
    'income' => [
        'code' => '3',
        'name' => 'Income',
        'parent' => null,
        'account_class' => 'revenue',
        'normal_balance' => 'credit',
    ],
    'liabilities' => [
        'code' => '4',
        'name' => 'Liabilities',
        'parent' => null,
        'account_class' => 'liability',
        'normal_balance' => 'credit',
    ],
    'fixed_asset' => [
        'code' => '10001',
        'name' => 'Fixed Asset',
        'parent' => 'assets',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'current_asset' => [
        'code' => '10002',
        'name' => 'Current Asset',
        'parent' => 'assets',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'account_receivable' => [
        'code' => '100020001',
        'name' => 'Account Receivable',
        'parent' => 'current_asset',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'land' => [
        'code' => '100010001',
        'name' => 'Land',
        'parent' => 'fixed_asset',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'current_liabilities' => [
        'code' => '40001',
        'name' => 'Current Liabilities',
        'parent' => 'liabilities',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
    ],
    'account_payable' => [
        'code' => '400010001',
        'name' => 'Account Payable',
        'parent' => 'current_liabilities',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
    ],
    'bank_loan' => [
        'code' => '400010002',
        'name' => 'Bank Loan',
        'parent' => 'current_liabilities',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
    ],
    'cash_in_hand' => [
        'code' => '100020002',
        'name' => 'Cash in hand',
        'parent' => 'current_asset',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'mobile_bank' => [
        'code' => '100020003',
        'name' => 'Mobile Bank',
        'parent' => 'current_asset',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'bank_account' => [
        'code' => '100020004',
        'name' => 'Bank Account',
        'parent' => 'current_asset',
        'account_class' => 'asset',
        'normal_balance' => 'debit',
    ],
    'employee_management' => [
        'code' => '40002',
        'name' => 'Employee Management',
        'parent' => 'liabilities',
        'account_class' => 'liability',
        'normal_balance' => 'credit',
    ],
];

$paymentGroups = [
    'Cash' => [$accountGroups['cash_in_hand']['code']],
    'Mobile Bank' => [$accountGroups['mobile_bank']['code']],
    'Bank' => [$accountGroups['bank_account']['code']],
];

$salesExcludedCategoryCodes = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SALE_EXCLUDED_CATEGORY_CODES', ''))
)));

$voucherCategories = [
    'prefix' => 'VC',
    'padding' => 3,
    'system' => [
        'customer' => [
            'code' => 'VC001',
            'name' => 'Customer',
            'legacy_names' => ['Customer'],
            'description' => 'Customer related payments and receipts.',
            'sort_order' => 1,
        ],
        'employee' => [
            'code' => 'VC002',
            'name' => 'Employee',
            'legacy_names' => ['Employee'],
            'description' => 'Employee related payments and receipts.',
            'sort_order' => 2,
        ],
        'supplier' => [
            'code' => 'VC003',
            'name' => 'Supplier',
            'legacy_names' => ['Supplier'],
            'description' => 'Supplier related payments and receipts.',
            'sort_order' => 3,
        ],
        'operating' => [
            'code' => 'VC004',
            'name' => 'Operating',
            'legacy_names' => ['Operating', 'Office'],
            'description' => 'Operating payments and receipts.',
            'sort_order' => 4,
        ],
        'finance' => [
            'code' => 'VC005',
            'name' => 'Finance',
            'legacy_names' => ['Finance', 'General', 'Liability'],
            'description' => 'Finance related payments and receipts.',
            'sort_order' => 5,
        ],
    ],
];

$voucherTransactionTypes = [
    'employee' => [
        'monthly_salary' => ['code' => '1001', 'name' => 'Monthly Salary', 'voucher_type' => 'payment'],
        'salary_advance' => ['code' => '1002', 'name' => 'Salary Advance', 'voucher_type' => 'payment'],
        'personal_loan' => ['code' => '1003', 'name' => 'Personal Loan', 'voucher_type' => 'payment'],
        'advance_return' => ['code' => '1008', 'name' => 'Advance Return', 'voucher_type' => 'receipt'],
        'loan_recovery' => ['code' => '1009', 'name' => 'Loan Recovery', 'voucher_type' => 'receipt'],
    ],
    'supplier' => [
        'cash_purchase' => ['code' => '1017', 'name' => 'Cash Purchase', 'voucher_type' => 'payment'],
        'credit_payment' => ['code' => '1018', 'name' => 'Credit Payment', 'voucher_type' => 'payment'],
        'advance_payment' => ['code' => '1019', 'name' => 'Advance Payment', 'voucher_type' => 'payment'],
        'security_deposit' => ['code' => '1020', 'name' => 'Security Deposit', 'voucher_type' => 'payment'],
    ],
    'customer' => [
        'advance_return' => ['code' => '1019', 'name' => 'Advance Return', 'voucher_type' => 'payment'],
        'security_deposit_refund' => [
            'code' => '1028',
            'name' => 'Security Deposit Refund',
            'voucher_type' => 'payment',
            'description' => 'Compatibility identifier used by the customer security deposit refund workflow.',
        ],
        'security_deposit' => ['code' => '1031', 'name' => 'Security Deposit', 'voucher_type' => 'receipt'],
        'due_paid' => ['code' => '1032', 'name' => 'Due Paid', 'voucher_type' => 'receipt'],
        'advance_payment' => ['code' => '1033', 'name' => 'Advance Payment', 'voucher_type' => 'receipt'],
    ],
    'finance' => [
        'owner_withdrawal' => ['code' => '1071', 'name' => 'Owner Withdrawal', 'voucher_type' => 'payment'],
        'opening_balance' => ['code' => '1072', 'name' => 'Opening Balance', 'voucher_type' => 'receipt'],
        'investment' => ['code' => '1073', 'name' => 'Investment', 'voucher_type' => 'receipt'],
        'capital_withdraw' => ['code' => '1074', 'name' => 'Capital Withdraw', 'voucher_type' => 'payment'],
    ],
];

return [
    /*
    |--------------------------------------------------------------------------
    | Reserved ERP Categories
    |--------------------------------------------------------------------------
    |
    | These codes are permanent business identifiers. Database category names
    | may be edited, but the configured codes must never be changed, reused,
    | or deleted.
    |
    */
    'categories' => [
        $productCategoryCodes['oil'] => 'Oil',
        $productCategoryCodes['gas'] => 'Gas',
        $productCategoryCodes['lubricant'] => 'Lubricant & Accessories',
    ],

    'product_categories' => $productCategoryCodes,

    'dispenser' => [
        'allowed_product_category_keys' => ['oil', 'gas'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Groups
    |--------------------------------------------------------------------------
    |
    | These codes define the permanent chart-of-accounts group hierarchy.
    | Database IDs are intentionally excluded because codes are the stable
    | business identifiers used by services, reports, and integrations.
    |
    */
    'account_groups' => [
        'system' => $accountGroups,
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher Categories
    |--------------------------------------------------------------------------
    |
    | System voucher categories use permanent codes. Their display names may
    | change in the database, but their codes remain stable across vouchers,
    | reports, ledgers, and integrations.
    |
    */
    'voucher' => [
        'types' => [
            'payment' => 'payment',
            'receipt' => 'receipt',
            'both' => 'both',
        ],
        'statuses' => [
            'draft' => 'draft',
            'posted' => 'posted',
            'reversed' => 'reversed',
            'void' => 'void',
        ],
        'categories' => $voucherCategories,
        'transaction_types' => [
            'code_padding' => 4,
            'system' => $voucherTransactionTypes,
        ],
    ],

    // Backward-compatible alias for existing voucher category integrations.
    'voucher_categories' => $voucherCategories,

    'sales' => [
        'max_items' => (int) env('SALE_MAX_ITEMS', 100),
        'currency_scale' => (int) env('SALE_CURRENCY_SCALE', 2),
        'payment_groups' => $paymentGroups,
        'excluded_category_codes' => $salesExcludedCategoryCodes,
    ],

    'accounting' => [
        'payment_groups' => $paymentGroups,
    ],

    'vehicle_products' => [
        'max_assigned' => (int) env('VEHICLE_MAX_PRODUCTS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Employee File Uploads
    |--------------------------------------------------------------------------
    |
    | Employee files are stored on the configured Laravel filesystem disk.
    | Database records contain only paths relative to the selected disk.
    | Upload size limits are expressed in kilobytes.
    |
    */
    'employee_uploads' => [
        'disk' => env('EMPLOYEE_UPLOAD_DISK', 'public'),
        'directory' => env('EMPLOYEE_UPLOAD_DIRECTORY', 'employees'),
        'image_max_kb' => (int) env('EMPLOYEE_IMAGE_MAX_KB', 5120),
        'nid_max_kb' => (int) env('EMPLOYEE_NID_MAX_KB', 10240),
    ],

    'company_documents' => [
        'disk' => env('COMPANY_DOCUMENT_DISK', 'private'),
        'directory' => env('COMPANY_DOCUMENT_DIRECTORY', 'company-documents'),
        'max_file_kb' => (int) env('COMPANY_DOCUMENT_MAX_FILE_KB', 10240),
    ],
];
