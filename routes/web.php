<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\EmpDesignationController;
use App\Http\Controllers\EmpTypeController;
use App\Http\Controllers\EmpDepartmentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PaymentVoucherController;
use App\Http\Controllers\ReceivedVoucherController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\CreditSaleController;
use App\Http\Controllers\OfficePaymentController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\DailyStatementController;
use App\Http\Controllers\CustomerSummaryBillController;
use App\Http\Controllers\CustomerDetailsBillController;
use App\Http\Controllers\CustomerLedgerSummaryController;
use App\Http\Controllers\CustomerLedgerDetailsController;
use App\Http\Controllers\DispenserController;
use App\Http\Controllers\DispenserReadingController;
use App\Http\Controllers\ShiftClosedListController;
use App\Http\Controllers\ProductRateController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\CustomerSalesReportController;
use App\Http\Controllers\PaymentSubTypeController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\CashBookLedgerController;
use App\Http\Controllers\BankBookLedgerController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LiabilityAssetsController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\SMSConfigController;
use App\Http\Controllers\SMSTemplateController;
use App\Http\Controllers\SMSLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonthlyDispenserReportController;
use App\Http\Controllers\WhiteSaleController;

Route::get('/', function () {
    return Inertia::render('home');
});

Route::get('/about', function () {
    return Inertia::render('about');
});

Route::get('/services', function () {
    return Inertia::render('services');
});

Route::get('/contact', function () {
    return Inertia::render('contact');
});

Route::get('/register', function () {
    return Inertia::render('auth/register');
})->middleware('check.registration');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart.data');
    
    // Permission routes
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    Route::delete('permissions/bulk/delete', [PermissionController::class, 'bulkDelete'])->name('permissions.bulk.delete');
    Route::get('permissions/download-pdf', [PermissionController::class, 'downloadPdf'])->name('permissions.download.pdf');
    
    // Role routes
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::delete('roles/bulk/delete', [RoleController::class, 'bulkDelete'])->name('roles.bulk.delete');
    Route::get('roles/download-pdf', [RoleController::class, 'downloadPdf'])->name('roles.download.pdf');
    
    // User routes
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::delete('users/bulk/delete', [UserController::class, 'bulkDelete'])->name('users.bulk.delete');
    Route::get('users/download-pdf', [UserController::class, 'downloadPdf'])->name('users.download.pdf');
    
    // Company Setting Routes
    Route::get('company-settings', [CompanySettingController::class, 'index'])->name('company-settings.index');
    Route::get('company-settings/create', [CompanySettingController::class, 'create'])->name('company-settings.create');
    Route::get('company-settings/download-pdf', [CompanySettingController::class, 'downloadPdf'])->name('company-settings.download.pdf');
    Route::delete('company-settings/bulk/delete', [CompanySettingController::class, 'bulkDelete'])->name('company-settings.bulk.delete');
    Route::post('company-settings', [CompanySettingController::class, 'store'])->name('company-settings.store');
    Route::get('company-settings/{companySetting}', [CompanySettingController::class, 'show'])->name('company-settings.show');
    Route::get('company-settings/{companySetting}/edit', [CompanySettingController::class, 'edit'])->name('company-settings.edit');
    Route::put('company-settings/{companySetting}', [CompanySettingController::class, 'update'])->name('company-settings.update');
    Route::delete('company-settings/{companySetting}', [CompanySettingController::class, 'destroy'])->name('company-settings.destroy');
    
    // Shift routes
    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/download-pdf', [ShiftController::class, 'downloadPdf'])->name('shifts.download.pdf');
    Route::delete('shifts/bulk/delete', [ShiftController::class, 'bulkDelete'])->name('shifts.bulk.delete');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    
    // Employee Designation routes
    Route::get('emp-designations', [EmpDesignationController::class, 'index'])->name('emp-designations.index');
    Route::get('emp-designations/download-pdf', [EmpDesignationController::class, 'downloadPdf'])->name('emp-designations.download.pdf');
    Route::delete('emp-designations/bulk/delete', [EmpDesignationController::class, 'bulkDelete'])->name('emp-designations.bulk.delete');
    Route::post('emp-designations', [EmpDesignationController::class, 'store'])->name('emp-designations.store');
    Route::put('emp-designations/{empDesignation}', [EmpDesignationController::class, 'update'])->name('emp-designations.update');
    Route::delete('emp-designations/{empDesignation}', [EmpDesignationController::class, 'destroy'])->name('emp-designations.destroy');
    
    // Employee Type routes
    Route::get('emp-types', [EmpTypeController::class, 'index'])->name('emp-types.index');
    Route::get('emp-types/download-pdf', [EmpTypeController::class, 'downloadPdf'])->name('emp-types.download.pdf');
    Route::delete('emp-types/bulk/delete', [EmpTypeController::class, 'bulkDelete'])->name('emp-types.bulk.delete');
    Route::post('emp-types', [EmpTypeController::class, 'store'])->name('emp-types.store');
    Route::put('emp-types/{empType}', [EmpTypeController::class, 'update'])->name('emp-types.update');
    Route::delete('emp-types/{empType}', [EmpTypeController::class, 'destroy'])->name('emp-types.destroy');
    
    // Employee Department routes
    Route::get('emp-departments', [EmpDepartmentController::class, 'index'])->name('emp-departments.index');
    Route::get('emp-departments/download-pdf', [EmpDepartmentController::class, 'downloadPdf'])->name('emp-departments.download.pdf');
    Route::delete('emp-departments/bulk/delete', [EmpDepartmentController::class, 'bulkDelete'])->name('emp-departments.bulk.delete');
    Route::post('emp-departments', [EmpDepartmentController::class, 'store'])->name('emp-departments.store');
    Route::put('emp-departments/{empDepartment}', [EmpDepartmentController::class, 'update'])->name('emp-departments.update');
    Route::delete('emp-departments/{empDepartment}', [EmpDepartmentController::class, 'destroy'])->name('emp-departments.destroy');
    
    // Unit routes
    Route::get('units', [UnitController::class, 'index'])->name('units.index');
    Route::get('units/download-pdf', [UnitController::class, 'downloadPdf'])->name('units.download.pdf');
    Route::delete('units/bulk/delete', [UnitController::class, 'bulkDelete'])->name('units.bulk.delete');
    Route::post('units', [UnitController::class, 'store'])->name('units.store');
    Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    
    // Category routes
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/download-pdf', [CategoryController::class, 'downloadPdf'])->name('categories.download.pdf');
    Route::delete('categories/bulk/delete', [CategoryController::class, 'bulkDelete'])->name('categories.bulk.delete');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    
    // Product routes
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/download-pdf', [ProductController::class, 'downloadPdf'])->name('products.download.pdf');
    Route::delete('products/bulk/delete', [ProductController::class, 'bulkDelete'])->name('products.bulk.delete');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    
    // Product Rate routes
    Route::get('product-rates', [ProductRateController::class, 'index'])->name('product-rates.index');
    Route::get('product-rates/download-pdf', [ProductRateController::class, 'downloadPdf'])->name('product-rates.download.pdf');
    Route::post('product-rates', [ProductRateController::class, 'store'])->name('product-rates.store');
    Route::put('product-rates/{productRate}', [ProductRateController::class, 'update'])->name('product-rates.update');
    Route::delete('product-rates/{productRate}', [ProductRateController::class, 'destroy'])->name('product-rates.destroy');
    Route::post('product-rates/bulk-delete', [ProductRateController::class, 'bulkDelete'])->name('product-rates.bulk.delete');
    
    // Dispenser routes
    Route::get('dispensers', [DispenserController::class, 'index'])->name('dispensers.index');
    Route::get('dispensers/download-pdf', [DispenserController::class, 'downloadPdf'])->name('dispensers.download.pdf');
    Route::delete('dispensers/bulk/delete', [DispenserController::class, 'bulkDelete'])->name('dispensers.bulk.delete');
    Route::post('dispensers', [DispenserController::class, 'store'])->name('dispensers.store');
    Route::put('dispensers/{dispenser}', [DispenserController::class, 'update'])->name('dispensers.update');
    Route::delete('dispensers/{dispenser}', [DispenserController::class, 'destroy'])->name('dispensers.destroy');
    
    // Dispenser Reading routes
    Route::get('product/dispensers-reading', [DispenserReadingController::class, 'index'])->name('dispensers-reading.index');
    Route::get('product/dispensers-reading/shifts/{date}', [DispenserReadingController::class, 'getShiftsByDate']);
    Route::get('product/get-shift-closing-data/{date}/{shift}', [DispenserReadingController::class, 'getShiftClosingData']);
    Route::post('product/dispensers-reading', [DispenserReadingController::class, 'store'])->name('dispensers-reading.store');
    
    // Shift Closed List routes
    Route::get('shift-closed-list', [ShiftClosedListController::class, 'index'])->name('shift-closed-list.index');
    Route::get('shift-closed-list/download-pdf', [ShiftClosedListController::class, 'downloadPdf'])->name('shift-closed-list.download.pdf');
    Route::get('shift-closed-list/{id}', [ShiftClosedListController::class, 'show'])->name('shift-closed-list.show');
    Route::get('shift-closed-list/{id}/download-pdf', [ShiftClosedListController::class, 'downloadShowPdf'])->name('shift-closed-list.show.download.pdf');
    Route::delete('shift-closed-list/{id}', [ShiftClosedListController::class, 'destroy'])->name('shift-closed-list.destroy');
    Route::delete('shift-closed-list/bulk/delete', [ShiftClosedListController::class, 'bulkDelete'])->name('shift-closed-list.bulk.delete');
    
    // Group routes
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/get-parentchild/{code}', [GroupController::class, 'getParentChild'])->name('groups.get-parentchild');
    Route::get('groups/download-pdf', [GroupController::class, 'downloadPdf'])->name('groups.download.pdf');
    Route::delete('groups/bulk/delete', [GroupController::class, 'bulkDelete'])->name('groups.bulk.delete');
    Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    
    // Account routes
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/download-pdf', [AccountController::class, 'downloadPdf'])->name('accounts.download.pdf');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    
    // Customer routes
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/download-pdf', [CustomerController::class, 'downloadPdf'])->name('customers.download.pdf');
    Route::delete('customers/bulk/delete', [CustomerController::class, 'bulkDelete'])->name('customers.bulk.delete');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    Route::get('customers/{customer}/sales-pdf', [CustomerController::class, 'downloadSalesPdf'])->name('customers.sales.pdf');
    Route::get('customers/{customer}/payments-pdf', [CustomerController::class, 'downloadPaymentsPdf'])->name('customers.payments.pdf');
    Route::post('customers/{customer}/send-sms', [CustomerController::class, 'sendSMS'])->name('customers.send.sms');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    
    // Vehicle routes
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/download-pdf', [VehicleController::class, 'downloadPdf'])->name('vehicles.download.pdf');
    Route::delete('vehicles/bulk/delete', [VehicleController::class, 'bulkDelete'])->name('vehicles.bulk.delete');
    Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
    
    // Employee routes
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/statement', [EmployeeController::class, 'statement'])->name('employees.statement');
    Route::get('employees/{employee}/payments-pdf', [EmployeeController::class, 'downloadPaymentsPdf'])->name('employees.payments.pdf');
    Route::get('employees/{employee}/receipts-pdf', [EmployeeController::class, 'downloadReceiptsPdf'])->name('employees.receipts.pdf');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::delete('employees/bulk/delete', [EmployeeController::class, 'bulkDelete'])->name('employees.bulk.delete');
    Route::get('employees/download-pdf', [EmployeeController::class, 'downloadPdf'])->name('employees.download.pdf');
    
    // Supplier routes
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/download-pdf', [SupplierController::class, 'downloadPdf'])->name('suppliers.download.pdf');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::delete('suppliers/bulk/delete', [SupplierController::class, 'bulkDelete'])->name('suppliers.bulk.delete');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');
    Route::get('suppliers/{supplier}/purchases-pdf', [SupplierController::class, 'downloadPurchasesPdf'])->name('suppliers.purchases.pdf');
    Route::get('suppliers/{supplier}/payments-pdf', [SupplierController::class, 'downloadPaymentsPdf'])->name('suppliers.payments.pdf');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    
    // Payment Voucher routes
    Route::get('vouchers/payment', [PaymentVoucherController::class, 'index'])->name('vouchers.payment.index');
    Route::get('vouchers/payment/download-pdf', [PaymentVoucherController::class, 'downloadPdf'])->name('vouchers.payment.download.pdf');
    Route::post('vouchers/payment', [PaymentVoucherController::class, 'store'])->name('vouchers.payment.store');
    Route::put('vouchers/payment/{voucher}', [PaymentVoucherController::class, 'update'])->name('vouchers.payment.update');
    Route::delete('vouchers/payment/{voucher}', [PaymentVoucherController::class, 'destroy'])->name('vouchers.payment.destroy');
    Route::delete('vouchers/payment/bulk/delete', [PaymentVoucherController::class, 'bulkDelete'])->name('vouchers.payment.bulk.delete');
    
    // Received Voucher routes
    Route::get('vouchers/received', [ReceivedVoucherController::class, 'index'])->name('vouchers.received.index');
    Route::post('vouchers/received', [ReceivedVoucherController::class, 'store'])->name('vouchers.received.store');
    Route::put('vouchers/received/{voucher}', [ReceivedVoucherController::class, 'update'])->name('vouchers.received.update');
    Route::delete('vouchers/received/{voucher}', [ReceivedVoucherController::class, 'destroy'])->name('vouchers.received.destroy');
    Route::delete('vouchers/received/bulk/delete', [ReceivedVoucherController::class, 'bulkDelete'])->name('vouchers.received.bulk.delete');
    Route::get('vouchers/received/download-pdf', [ReceivedVoucherController::class, 'downloadPdf'])->name('vouchers.received.download.pdf');
    
    // Purchase routes
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/download-pdf', [PurchaseController::class, 'downloadPdf'])->name('purchases.download.pdf');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::delete('purchases/bulk/delete', [PurchaseController::class, 'bulkDelete'])->name('purchases.bulk.delete');

    // Sale routes
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
    Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::delete('sales/bulk/delete', [SaleController::class, 'bulkDelete'])->name('sales.bulk.delete');
    Route::get('sales/download-pdf', [SaleController::class, 'downloadPdf'])->name('sales.download.pdf');

    Route::get('sales/batch/{batchCode}/pdf', [SaleController::class, 'downloadBatchPdf'])->name('sales.batch.pdf');
    
    // Credit Sale routes
    Route::get('credit-sales', [CreditSaleController::class, 'index'])->name('credit-sales.index');
    Route::get('credit-sales/download-pdf', [CreditSaleController::class, 'downloadPdf'])->name('credit-sales.download.pdf');
    Route::post('credit-sales', [CreditSaleController::class, 'store'])->name('credit-sales.store');
    Route::get('credit-sales/{creditSale}/edit', [CreditSaleController::class, 'edit'])->name('credit-sales.edit');
    Route::put('credit-sales/{creditSale}', [CreditSaleController::class, 'update'])->name('credit-sales.update');
    Route::delete('credit-sales/{creditSale}', [CreditSaleController::class, 'destroy'])->name('credit-sales.destroy');
    Route::delete('credit-sales/bulk/delete', [CreditSaleController::class, 'bulkDelete'])->name('credit-sales.bulk.delete');
    
    // Office Payment routes
    Route::get('office-payments', [OfficePaymentController::class, 'index'])->name('office-payments.index');
    Route::get('office-payments/download-pdf', [OfficePaymentController::class, 'downloadPdf'])->name('office-payments.download.pdf');
    Route::post('office-payments', [OfficePaymentController::class, 'store'])->name('office-payments.store');
    Route::put('office-payments/{officePayment}', [OfficePaymentController::class, 'update'])->name('office-payments.update');
    Route::delete('office-payments/{officePayment}', [OfficePaymentController::class, 'destroy'])->name('office-payments.destroy');
    Route::delete('office-payments/bulk/delete', [OfficePaymentController::class, 'bulkDelete'])->name('office-payments.bulk.delete');
    
    // Stock routes
    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::post('stocks', [StockController::class, 'store'])->name('stocks.store');
    Route::put('stocks/{stock}', [StockController::class, 'update'])->name('stocks.update');
    Route::delete('stocks/{stock}', [StockController::class, 'destroy'])->name('stocks.destroy');
    Route::delete('stocks/bulk/delete', [StockController::class, 'bulkDelete'])->name('stocks.bulk.delete');
    Route::get('stocks/download-pdf', [StockController::class, 'downloadPdf'])->name('stocks.download.pdf');
    
    // Stock Report routes
    Route::get('stock-report', [StockReportController::class, 'index'])->name('stock-report.index');
    Route::get('stock-report/download-pdf', [StockReportController::class, 'downloadPdf'])->name('stock-report.download.pdf');
    
    // Daily Statement routes
    Route::get('daily-statement', [DailyStatementController::class, 'index'])->name('daily-statement.index');
    Route::get('daily-statement/download-pdf', [DailyStatementController::class, 'downloadPdf'])->name('daily-statement.download.pdf');
    
    // Customer Summary Bill routes
    Route::get('customer-summary-bill', [CustomerSummaryBillController::class, 'index'])->name('customer-summary-bill.index');
    Route::get('customer-summary-bill/download-pdf', [CustomerSummaryBillController::class, 'downloadPdf'])->name('customer-summary-bill.download.pdf');
    
    // Customer Details Bill routes
    Route::get('customer-details-bill', [CustomerDetailsBillController::class, 'index'])->name('customer-details-bill.index');
    Route::get('customer-details-bill/download-pdf', [CustomerDetailsBillController::class, 'downloadPdf'])->name('customer-details-bill.download.pdf');
    
    // Customer Ledger Summary routes
    Route::get('customer-ledger-summary', [CustomerLedgerSummaryController::class, 'index'])->name('customer-ledger-summary.index');
    Route::get('customer-ledger-summary/download-pdf', [CustomerLedgerSummaryController::class, 'downloadPdf'])->name('customer-ledger-summary.download.pdf');
    
    // Customer Ledger Details routes
    Route::get('customer-ledger-details/{customer}', [CustomerLedgerDetailsController::class, 'index'])->name('customer-ledger-details.index');
    Route::get('customer-ledger-details/{customer}/download-pdf', [CustomerLedgerDetailsController::class, 'downloadPdf'])->name('customer-ledger-details.download.pdf');
    
    // Purchase Report Details routes
    Route::get('purchase-report-details', [PurchaseReportController::class, 'index'])->name('purchase-report-details.index');
    Route::get('purchase-report-details/download-pdf', [PurchaseReportController::class, 'downloadPdf'])->name('purchase-report-details.download.pdf');
    
    // Customer Sales Reports routes
    Route::get('customer-wise-sales-reports', [CustomerSalesReportController::class, 'index'])->name('customer-sales-reports.index');
    Route::get('customer-wise-sales-reports/download-pdf', [CustomerSalesReportController::class, 'downloadPdf'])->name('customer-sales-reports.download.pdf');
    
    // Bank Book Ledger routes
    Route::get('bank-book-ledger', [BankBookLedgerController::class, 'index'])->name('bank-book-ledger.index');
    Route::get('bank-book-ledger/download-pdf', [BankBookLedgerController::class, 'downloadPdf'])->name('bank-book-ledger.download.pdf');
    Route::get('bank-book-ledger/{ac_number}', [BankBookLedgerController::class, 'show'])->name('bank-book-ledger.show');
    Route::get('bank-book-ledger/{ac_number}/download-pdf', [BankBookLedgerController::class, 'downloadAccountPdf'])->name('bank-book-ledger.account.download.pdf');
    
    // Cash Book Ledger routes
    Route::get('cash-book-ledger', [CashBookLedgerController::class, 'index'])->name('cash-book-ledger.index');
    Route::get('cash-book-ledger/download-pdf', [CashBookLedgerController::class, 'downloadPdf'])->name('cash-book-ledger.download.pdf');
    Route::get('cash-book-ledger/{id}', [CashBookLedgerController::class, 'show'])->name('cash-book-ledger.show');
    Route::get('cash-book-ledger/{id}/download-pdf', [CashBookLedgerController::class, 'downloadShiftPdf'])->name('cash-book-ledger.shift.download.pdf');
    
    // General Ledger routes
    Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
    Route::get('general-ledger/download-pdf', [GeneralLedgerController::class, 'downloadPdf'])->name('general-ledger.download.pdf');
    
    // Payment Sub Type routes
    Route::get('payment-sub-types', [PaymentSubTypeController::class, 'index'])->name('payment-sub-types.index');
    Route::get('payment-sub-types/{paymentSubType}/edit', [PaymentSubTypeController::class, 'edit'])->name('payment-sub-types.edit');
    Route::post('payment-sub-types', [PaymentSubTypeController::class, 'store'])->name('payment-sub-types.store');
    Route::put('payment-sub-types/{paymentSubType}', [PaymentSubTypeController::class, 'update'])->name('payment-sub-types.update');
    Route::delete('payment-sub-types/{paymentSubType}', [PaymentSubTypeController::class, 'destroy'])->name('payment-sub-types.destroy');
    Route::delete('payment-sub-types/bulk/delete', [PaymentSubTypeController::class, 'bulkDelete'])->name('payment-sub-types.bulk.delete');
    Route::get('payment-sub-types/download-pdf', [PaymentSubTypeController::class, 'downloadPdf'])->name('payment-sub-types.download.pdf');
    
    // Loan routes
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('loans/{account}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('loans/{account}/statement', [LoanController::class, 'statement'])->name('loans.statement');
    Route::get('loans/{account}/statement-pdf', [LoanController::class, 'downloadStatementPdf'])->name('loans.statement.pdf');
    Route::get('loans/{account}/loans-pdf', [LoanController::class, 'downloadLoansPdf'])->name('loans.loans.pdf');
    Route::get('loans/{account}/payments-pdf', [LoanController::class, 'downloadPaymentsPdf'])->name('loans.payments.pdf');
    
    // Liability and Assets routes
    Route::get('liability-assets', [LiabilityAssetsController::class, 'index'])->name('liability-assets.index');
    Route::get('liability-assets/download-pdf', [LiabilityAssetsController::class, 'downloadPdf'])->name('liability-assets.download.pdf');
    
    // Balance Sheet routes
    Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
    Route::get('balance-sheet/download-pdf', [BalanceSheetController::class, 'downloadPdf'])->name('balance-sheet.download.pdf');
    
    // SMS Config routes
    Route::get('sms-configs', [SMSConfigController::class, 'index'])->name('sms-configs.index');
    Route::get('sms-configs/{smsConfig}/edit', [SMSConfigController::class, 'edit'])->name('sms-configs.edit');
    Route::post('sms-configs', [SMSConfigController::class, 'store'])->name('sms-configs.store');
    Route::put('sms-configs/{smsConfig}', [SMSConfigController::class, 'update'])->name('sms-configs.update');
    Route::delete('sms-configs/{smsConfig}', [SMSConfigController::class, 'destroy'])->name('sms-configs.destroy');
    Route::delete('sms-configs/bulk/delete', [SMSConfigController::class, 'bulkDelete'])->name('sms-configs.bulk.delete');
    Route::get('sms-configs/download-pdf', [SMSConfigController::class, 'downloadPdf'])->name('sms-configs.download.pdf');
    
    // SMS Template routes
    Route::get('sms-templates', [SMSTemplateController::class, 'index'])->name('sms-templates.index');
    Route::get('sms-templates/{smsTemplate}/edit', [SMSTemplateController::class, 'edit'])->name('sms-templates.edit');
    Route::post('sms-templates', [SMSTemplateController::class, 'store'])->name('sms-templates.store');
    Route::put('sms-templates/{smsTemplate}', [SMSTemplateController::class, 'update'])->name('sms-templates.update');
    Route::delete('sms-templates/{smsTemplate}', [SMSTemplateController::class, 'destroy'])->name('sms-templates.destroy');
    Route::delete('sms-templates/bulk/delete', [SMSTemplateController::class, 'bulkDelete'])->name('sms-templates.bulk.delete');
    Route::get('sms-templates/download-pdf', [SMSTemplateController::class, 'downloadPdf'])->name('sms-templates.download.pdf');
    
    // SMS Log routes
    Route::get('sms-logs', [SMSLogController::class, 'index'])->name('sms-logs.index');
    Route::delete('sms-logs/{smsLog}', [SMSLogController::class, 'destroy'])->name('sms-logs.destroy');
    Route::delete('sms-logs/bulk/delete', [SMSLogController::class, 'bulkDelete'])->name('sms-logs.bulk.delete');
    
    // White Sale routes
    Route::get('white-sales', [WhiteSaleController::class, 'index'])->name('white-sales.index');
    Route::get('white-sales/customer/{mobile}', [WhiteSaleController::class, 'getCustomerByMobile']);
    Route::get('white-sales/create', [WhiteSaleController::class, 'create'])->name('white-sales.create');
    Route::post('white-sales', [WhiteSaleController::class, 'store'])->name('white-sales.store');
    Route::get('white-sales/{whiteSale}', [WhiteSaleController::class, 'show'])->name('white-sales.show');
    Route::get('white-sales/{whiteSale}/edit', [WhiteSaleController::class, 'edit'])->name('white-sales.edit');
    Route::put('white-sales/{whiteSale}', [WhiteSaleController::class, 'update'])->name('white-sales.update');
    Route::delete('white-sales/{whiteSale}', [WhiteSaleController::class, 'destroy'])->name('white-sales.destroy');
    Route::delete('white-sales/bulk/delete', [WhiteSaleController::class, 'bulkDelete'])->name('white-sales.bulk.delete');
    Route::get('white-sales/{whiteSale}/pdf', [WhiteSaleController::class, 'downloadSinglePdf'])->name('white-sales.single.pdf');
    Route::get('white-sales/download-pdf', [WhiteSaleController::class, 'downloadPdf'])->name('white-sales.download.pdf');
    
    // Monthly Dispenser Report routes
    Route::get('reports/monthly-dispenser-report', [MonthlyDispenserReportController::class, 'index'])->name('monthly-dispenser-report.index');
    Route::get('reports/monthly-dispenser-report/download-pdf', [MonthlyDispenserReportController::class, 'downloadPdf'])->name('monthly-dispenser-report.download.pdf');
});

require __DIR__.'/settings.php';
