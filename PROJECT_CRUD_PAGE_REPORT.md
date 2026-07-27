# Project CRUD, Form, Table and Report Page Inventory

Audit date: Monday, July 27, 2026

## 1. Scope and Counting

এই report তৈরি করতে নিচের জায়গাগুলো cross-check করা হয়েছে:

- `resources/js/components/app-sidebar.tsx`
- `routes/web.php` এবং `routes/settings.php`
- `app/Http/Controllers/*`
- `resources/js/pages/**/*`
- `resources/views/pdf/**/*`
- Laravel-এর generated route list

বর্তমান project snapshot:

- মোট Laravel route: **305**
- Authenticated route: **289**
- Sidebar direct link: **46**
- Sidebar-এর operational/CRUD/workflow page: **32**
- Sidebar-এর dashboard: **1**
- Sidebar-এর report/read-only page: **13**
- Controller থেকে render হওয়া unique authenticated React screen: **64**
- React page component: **84** (public, auth, settings, modal component-সহ)
- PDF/print route: **59**
- PDF Blade template: **58**

CRUD shorthand:

- `C` = Create
- `R` = Read/List/Show
- `U` = Update
- `D` = Delete
- `Bulk D` = Bulk Delete
- `PDF` = PDF/print output

## 2. Sidebar CRUD and Workflow Pages

### General Setting

#### Company Setting

- Route/page: `/company-settings` -> `CompanySettings/CompanySettings.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; create/show/update আলাদা non-sidebar screen।
- Filters: Search, Status, Start Date, End Date.
- Create/update fields: Company Name, Company Details, Proprietor Name, Company Address, Factory Address, Cell Number, Phone Number, E-mail, Trade License No, e-TIN No, BIN No, VAT No, VAT Rate, Currency, User Registration, Status, Company Logo.
- Table columns: Select, SL, Company Name, E-mail, Cell Number, Phone Number, Status, Actions.

#### Shift

- Route/page: `/shifts` -> `Shifts/Shifts.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Status, Start Date, End Date.
- Form fields: Shift Name, Start Time, End Time, Status.
- Table columns: Select, Name, Start Time, End Time, Status, Actions.

### Dispenser

#### Credit Sales

- Route/page: `/credit-sales` -> `CreditSales/Index.tsx`, form in `CreditSales/CreditSaleModal.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; batch line-item create এবং single-row edit.
- Filters: Search, Customer, Payment Status, Start Date, End Date.
- Header/form fields: Sale Date, Shift, Memo No.
- Line-item fields: Customer, Vehicle, Product, Sales Price, Quantity, Amount, Due Amount, Remarks.
- Entry-grid columns: SL, Customer, Vehicle, Product Name, Quantity, Total, Action.
- List columns: Select, Date, Shift, Invoice No, Customer, Product, Vehicle, Quantity, Total Amount, Due Amount, Status, Actions.

#### Dispensers Calculation

- Route/page: `/product/dispensers-reading` -> `Dispensers/DispensersReading.tsx`
- Capability: shift-closing transaction `C/R`; normal update/delete route নেই।
- Main fields: Transaction Date, Select Shift.
- Dispenser row inputs: New Reading, Meter Test, Reading By; Old Reading এবং Item Rate calculated/reference value.
- Dispenser table: SL, Dispenser Name, Product ID, Product Name, Item Rate, Old Reading, New Reading, Meter Test, Reading By, Net Reading, Total Sales.
- Other-product inputs/table: Product Name, Product ID, Unit, Item Rate, In Stock, Sell, New Stock, Sell By, Total Sales.
- Product summary table: Product ID, Product Name, Rate, Quantity, Total Sale, Credit Sales, Bank Sales, Cash Sales.
- Settlement fields: Credit Sales, Bank Sales, Cash Sales, Credit/Bank/Cash Sales (Other Products), Cash Receive, Bank Receive, Total Cash, Cash Payment, Bank Payment, Office Payment, Final Due Amount.
- Office-payment subform: Date, Shift, Payment Type, To Account (Office), Amount, Remarks.

#### Dispensers Setting

- Route/page: `/dispensers` -> `Dispensers/Dispensers.tsx`
- Capability: `C/R/U/D`, `Bulk D`; PDF route declared, কিন্তু referenced `pdf.dispensers` template নেই।
- Filters: Search, Status, Product, Start Date, End Date.
- Form fields: Dispenser Name, Product, Opening Reading, Status.
- Table columns: Select, Dispenser Name, Product ID, Dispenser Items, Dispenser Items Rate, Start Reading, Status, Actions.

#### Shift Closed List

- Route/page: `/shift-closed-list` -> `ShiftClosedList/Index.tsx`
- Capability: `R/D`, `Bulk D`, `PDF`; detail page এবং detail PDF আছে।
- Filters: Search, Shift, Start Date, End Date.
- Table columns: Select, Date, Shift, Credit Sales, Bank Sales, Cash Sales, Total Cash, Cash Payment, Office Payment, Final Due, Actions.

### Customer

#### Customers

- Route/page: `/customers` -> `Customers/Customers.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; detail, statement, sales PDF, payments PDF এবং SMS action আছে।
- Filters: Search, Status.
- Customer fields: Name, Email, Mobile, Address, Previous Due, Security Deposit, NID Card/NID Number, Status.
- Additional backend-supported fields: VAT Registration No, TIN No, Trade License, Discount Rate, Credit Limit.
- Optional first-vehicle fields: Vehicle Type, Vehicle Name, Vehicle Number, Registration Date, Products.
- Table columns: Select, SL, Name, Account Number, Mobile, Total Sale, Total Payment, Total, Status, Actions.

#### Vehicles

- Route/page: `/vehicles` -> `Vehicles/Vehicles.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Customer, Status.
- Form fields: Customer, Vehicle Type, Vehicle Name, Vehicle Number, Registration Date, Products, Status.
- Table columns: Select, SL, Customer, Vehicle Name, Vehicle Number, Type, Products, Status, Actions.

### Supplier

#### Suppliers

- Route/page: `/suppliers` -> `Suppliers/Suppliers.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; detail, statement, purchases PDF এবং payments PDF আছে।
- Filters: Search, Status.
- Form fields: Name, Email, Mobile, Proprietor Name, Address, Status.
- Table columns: Select, SL, Name, Account Number, Mobile, Total Purchases, Total Payment, Total, Status, Actions.

### Products

#### Products

- Route/page: `/products` -> `Products/Products.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Status, Category, Unit, Start Date, End Date.
- Form fields: Product Code, Product Name, Category, Unit, Country of Origin, Remarks, Status.
- Backend field: Product Slug.
- Table columns: Select, Code, Product Name, Category, Unit, Purchase Price, Sales Price, Status, Actions.

#### Product Rates

- Route/page: `/product-rates` -> `ProductRates/Index.tsx`
- Capability: `C/R/U/D`, bulk delete via `POST`, `PDF`; modal form.
- Filters: Search, Status, Product, Start Date, End Date.
- Form fields: Product, Effective Date, Purchase Price, Sales Price, Status.
- Table columns: Select, Product, Purchase Price, Sales Price, Effective Date, Status, Actions.

#### Categories

- Route/page: `/categories` -> `Categories/Categories.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Status, Start Date, End Date.
- Form fields: Category Name, Category Code, Status.
- Table columns: Select, Category Name, Code, Status, Actions.

#### Units

- Route/page: `/units` -> `Units/Units.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Status, Start Date, End Date.
- Form fields: Unit Name, Unit Value, Status.
- Table columns: Select, Name, Value, Status, Actions.

### Product Stock

#### Stocks

- Route/page: `/stocks` -> `Stocks/Stocks.tsx`
- Capability: `C/R/U/D`, `Bulk D`; PDF route declared, কিন্তু referenced `pdf.stocks` template নেই।
- Filters: Search, Category, Start Date, End Date.
- Form fields: Product, Current Stock, Available Stock.
- Table columns: Select, Product Code, Product Name, Category, Unit, Current Stock, Available Stock, Actions.

### Purchase

#### Purchase

- Route/page: `/purchases` -> `Purchases/Index.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; batch line-item create এবং single-purchase edit.
- Filters: Search, Supplier, Payment Status, Start Date, End Date.
- Header fields: Purchase Date, Memo No, Remarks.
- Line-item fields: Supplier, Product, Present Stock, Product Name, Unit Price, Quantity, Amount, Payment Method, From Account, Paid Amount, Due Amount.
- Conditional payment fields: Bank Type, Bank Name, Cheque No, Mobile Bank, Mobile Number.
- Entry-grid columns: SL, Product Name, Quantity, Unit Price, Total, Payment, Paid, Due, Action.
- List columns: Select, Date, Supplier, Invoice No, Memo No, Total Amount, Paid Amount, Due Amount, Status, Actions.

### Sales

#### Sales

- Route/page: `/sales` -> `Sales/Index.tsx`, form in `Sales/SaleModal.tsx`
- Capability: `C/R/U/D`, `Bulk D`, list PDF, batch invoice PDF.
- Filters: Search, Customer, Payment Status, Start Date, End Date.
- Header fields: Sale Date, Shift, Memo No.
- Line-item fields: Customer, Vehicle, Mobile Number, Product, Sales Price, Quantity, Amount, Payment Method, To Account, Paid Amount, Remarks.
- Conditional payment fields: Bank Type, Bank Name, Cheque No, Mobile Bank.
- Entry-grid columns: SL, Customer, Vehicle, Product Name, Quantity, Total, Action.
- List columns: Select, Date, Shift, Invoice No, Customer, Product, Vehicle, Quantity, Total Amount, Paid Amount, Payment Type, Status, Actions.

#### White Sales

- Route/page: `/white-sales` -> `WhiteSales/Index.tsx`
- Capability: `C/R/U/D`, `Bulk D`, single invoice PDF; list PDF route declared হলেও controller method নেই।
- Filters: Search, Start Date, End Date.
- Header/customer fields: Shift, Mobile No, Company Name, Proprietor Name, Status, Send SMS flag.
- Product fields: Product, Sales Price, Unit, Quantity, Amount, Remarks.
- Entry-grid columns: SL, Product, Sales Price, Unit, Quantity, Amount, Action.
- List columns: Select, Date, Time, Invoice No, Shift, Products, Total Amount, Status, Actions.

### Accounts

#### Groups

- Route/page: `/groups` -> `Groups/Groups.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form এবং parent-child lookup.
- Filters: Search, Master Group, Status.
- Form fields: Select Master Group, Under Main Group, Group Name, Status.
- Table columns: Select, ID, Code, Name, Parent Group, Status, Actions.

#### Accounts

- Route/page: `/accounts` -> `Accounts/Accounts.tsx`
- Capability: list-page modal `C/R/U/D`, `PDF`; bulk delete route নেই।
- Filters: Search, Group, Status.
- Create fields: Name, Group, Status; Account Number auto-generated.
- Update fields: Name, Account Number, Group, Status.
- Table columns: SL, Name, Account Number, Group, Status, Actions.

#### Received Voucher

- Route/page: `/vouchers/received` -> `Vouchers/ReceivedVoucher.tsx`, form in `ReceivedVoucherModal.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; multi-line voucher create.
- Filters: Search, Payment Method, Start Date, End Date.
- Header fields: Date, Shift.
- Line-item fields: Category, Payment Sub Type, Payment Method, From Account (Payer), To Account (Receiver), Amount, Remarks.
- Conditional fields: Bank Type, Bank Name, Branch Name, Account No, Cheque No, Cheque Date, Mobile Bank, Mobile Number.
- Entry-grid columns: SL, Category, From, To, Amount, Method, Action.
- List columns: Select, Date, Shift, From Account, To Account, Amount, Category, Sub Type, Payment Method, Actions.

#### Payment Voucher

- Route/page: `/vouchers/payment` -> `Vouchers/PaymentVoucher.tsx`, form in `PaymentVoucherModal.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; multi-line voucher create.
- Filters: Search, Payment Method, Start Date, End Date.
- Header fields: Date, Shift.
- Line-item fields: Category, Payment Sub Type, Payment Method, From Account, To Account, Amount, Remarks.
- Conditional fields: Bank Type, Bank Name, Branch Name, Account No, Cheque Number, Cheque Date, Mobile Bank, Mobile Number.
- Entry-grid columns: SL, Category, From, To, Amount, Method, Action.
- List columns: Select, Date, Shift, From Account, To Account, Amount, Category, Sub Type, Payment Method, Actions.

#### Office Payment

- Route/page: `/office-payments` -> `OfficePayments/Index.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Start Date, End Date, Type.
- Form fields: Shift, Date, Payment Type, To Account (Office), Amount, Remarks.
- Table columns: Select, Date, Shift, To Account, Amount, Remarks, Actions.

### Employee

#### Employees

- Route/page: `/employees` -> `Employee/Index.tsx`
- Capability: `C/R/U/D`, `Bulk D`; dedicated create/show/update/statement screens; list PDF route-এর referenced `pdf.employees` template নেই।
- Filters: Search, Department, Designation, Type.
- Form fields: Full Name, Employee Code, Mobile, Alternative Mobile, Email, Department, Designation, Employee Type, Joining Date, Job Status, Salary, Date of Birth, Gender, Blood Group, Marital Status, Religion, NID Number, Father's Name, Mother's Name, Emergency Contact Person, Emergency Contact Number, Highest Education, Present Address, Permanent Address, Status.
- Backend-only/extra field: Order.
- Table columns: Select, Name, Account Number, Email, Phone, Department, Designation, Status, Actions.

#### Employee Type

- Route/page: `/emp-types` -> `EmpTypes/EmpTypes.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`.
- Filters: Search, Status, Start Date, End Date.
- Form fields: Employee Type Name, Status.
- Table columns: Select, Employee Type Name, Status, Actions.

#### Department

- Route/page: `/emp-departments` -> `EmpDepartments/EmpDepartments.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`.
- Filters: Search, Employee Type, Status, Start Date, End Date.
- Form fields: Department Name, Employee Type, Status.
- Table columns: Select, Department Name, Employee Type, Status, Actions.

#### Designation

- Route/page: `/emp-designations` -> `EmpDesignations/EmpDesignations.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`.
- Filters: Search, Status, Start Date, End Date.
- Form fields: Designation Name, Status.
- Table columns: Select, Designation Name, Status, Actions.

### User Management

#### Users

- Route/page: `/users` -> `Users/Users.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Role, Status, Start Date, End Date.
- Create fields: Name, Email, Password, Roles, Email Verified, Banned.
- Update fields: Name, Email, Password (optional), Roles, Email Verified, Banned.
- Table columns: Select, Name, Email, Role, Verified, Banned, Actions.

#### Roles

- Route/page: `/roles` -> `Roles/Roles.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Start Date, End Date.
- Form fields: Role Name, Permissions.
- Table columns: Select, Name, Description, Permissions, Users, Actions.
- Note: controller validation persists Name and Permissions; Description is displayed but is not in the current store/update validation contract.

#### Permissions

- Route/page: `/permissions` -> `Permissions/Permissions.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Module, Start Date, End Date.
- Form fields: Permission Name; backend also accepts Guard Name.
- Table columns: Select, Name, Module, Description, Roles, Actions.

### SMS Config

#### SMS Config

- Route/page: `/sms-configs` -> `SMS/SMSConfig.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search, Status, Start Date, End Date.
- Form fields: URL, API Key, Sender ID, Status.
- Table columns: Select, URL, API Key, Sender ID, Status, Actions.

#### SMS Template

- Route/page: `/sms-templates` -> `SMS/SMSTemplate.tsx`
- Capability: `C/R/U/D`, `Bulk D`, `PDF`; modal form.
- Filters: Search.
- Form fields: Title, Type, Message, Status.
- Table columns: Select, Title, Type, Message, Status, Created At, Actions.

#### SMS Logs

- Route/page: `/sms-logs` -> `SMS/SMSLogs.tsx`
- Capability: `R/D`, `Bulk D`; create/update/PDF নেই।
- Filters: Search, Status, Start Date, End Date.
- Detail fields: Phone Number, Message, Template, Sender ID, Sent At, Error Message.
- Table columns: Select, Phone Number, Message, Template, Status, Sent At, Actions.

## 3. Sidebar Report and Read-only Pages

### Dashboard

- Route/page: `/dashboard` -> `dashboard.tsx`
- Type: KPI/chart dashboard; CRUD table নয়।
- Data areas: financial overview, sales/purchase/stock/customer summaries এবং chart-data endpoint.

### Monthly Dispenser Report

- Route/page: `/reports/monthly-dispenser-report` -> `Reports/MonthlyDispenserReport.tsx`
- Filters: Search, Product, Shift, Start Date, End Date.
- Columns: SL, Date, Shift; প্রতি product-এর Total Sale, Price, Amount; Received (Due Paid), Amount, Credit Sale, Bank Sale, Expenses, Purchase, Cash in Hand, Total Balance.
- Features: dynamic column visibility, totals, PDF.

### Customer Details Bill

- Route/page: `/customer-details-bill` -> `CustomerDetailsBill/Index.tsx`
- Filters: Customer, Start Date, End Date.
- Columns: Date, Invoice No, Product, Unit, Price, Quantity, Total; Vehicle grouped/displayed with bill data.
- Output: PDF.

### Customer Summary Bill

- Route/page: `/customer-summary-bill` -> `CustomerSummaryBill/Index.tsx`
- Filters: Customer, Start Date, End Date.
- Columns: Date, Vehicle, Invoice No, Product, Unit, Price, Quantity, Total.
- Output: PDF.

### Daily Statement Report

- Route/page: `/daily-statement` -> `DailyStatement/Index.tsx`
- Filters: Start Date, End Date, Shift.
- Product-section columns: Product Name, Unit Name, Unit Price, Quantity, Total Amount.
- Customer credit-section columns: Customer Name, Vehicle Number, Product Name, Unit Name, Unit Price, Quantity, Total Amount.
- Received-section columns: SL, Name, Received Type, Received Amount.
- Payment-section columns: SL, Name, Payment Type, Payment Amount.
- Output: PDF.

### Today Stock Report

- Route/page: `/stock-report` -> `StockReport/Index.tsx`
- Filters: Search, Category.
- Columns: Product Code, Product Name, Category, Unit, Current Stock, Available Stock.
- Output: PDF.

### Purchase Report Details

- Route/page: `/purchase-report-details` -> `Reports/PurchaseReportDetails.tsx`
- Filters: Start Date, End Date.
- Columns: Supplier, Invoice No, Supplier Invoice/Memo, Product Name, Unit, Quantity, Price, Total.
- Output: PDF.

### Customer Sales Reports

- Route/page: `/customer-wise-sales-reports` -> `Reports/CustomerSalesReports.tsx`
- Filters: Customer, Start Date, End Date.
- Columns: Date, Customer, Shift, Invoice No, Quantity, Amount, Total Amount.
- Output: PDF.

### Liability and Assets

- Route/page: `/liability-assets` -> `LiabilityAssets/Index.tsx`
- Filters: নেই।
- Columns: Account Name, Type, Balance.
- Output: PDF.

### Balance Sheet

- Route/page: `/balance-sheet` -> `BalanceSheet/Index.tsx`
- Filters: Start Date, End Date.
- Purchase section: Product, Purchase Price, Total Liter, Total Amount.
- Sale/profit section: Product, Purchase Price, Sale Price, Effective Date, Total Liter, Total Amount, Total Profit.
- Liability/assets section: Description, Amount.
- Expense section: Expense Type, Amount.
- Output: PDF.

### General Ledger

- Route/page: `/general-ledger` -> `GeneralLedger/Index.tsx`
- Filters: Account, Start Date, End Date.
- Columns: Date, Transaction ID, Description, Payment Type, Debit, Credit, Balance.
- Output: PDF.

### Cash Book Ledger

- Route/page: `/cash-book-ledger` -> `CashBookLedger/Index.tsx`
- Filters: Shift, Start Date, End Date.
- Columns: Date, Shift, Cash Payment, Cash Received, Actions.
- Detail route: `/cash-book-ledger/{id}`.
- Output: list PDF এবং shift-detail PDF.

### Bank Book Ledger

- Route/page: `/bank-book-ledger` -> `BankBookLedger/Index.tsx`
- Filters: Start Date, End Date.
- Columns: SL, Account Name, Account Number, Group, Actions.
- Detail route: `/bank-book-ledger/{ac_number}`.
- Output: list PDF এবং account-detail PDF.

### Loan List

- Route/page: `/loans` -> `loans/index.tsx`
- Type: loan-account summary; direct loan create/update/delete নেই।
- Filters: Search, Status.
- Columns: SL, Account Name, Account Number, Total Loan, Total Payment, Total Payable, Actions.
- Detail/statement এবং তিন ধরনের PDF output আছে।

## 4. Non-sidebar Business Pages

### Hidden Full CRUD Page

#### Payment Sub Types

- Route/page: `/payment-sub-types` -> `PaymentSubTypes/PaymentSubType.tsx`
- Sidebar status: app sidebar-এ link নেই।
- Capability: `C/R/U/D`, `Bulk D`; PDF route declared, কিন্তু referenced `pdf.payment-sub-types` template নেই।
- Filters: Search, Category, Type, Status.
- Form fields: Code, Name, Voucher Category, Type, Status.
- Table columns: Select, Code, Name, Category, Type, Status, Actions.

### Dedicated Create, Show, Edit and Statement Screens

#### Company Settings

- `/company-settings/create` -> `CompanySettings/Create.tsx`; fields মূল Company Setting section-এর সমান।
- `/company-settings/{companySetting}` -> `CompanySettings/Show.tsx`; read-only Show screen.
- `/company-settings/{companySetting}/edit` -> `CompanySettings/Update.tsx`; Update screen.

#### Customer

- `/customers/{customer}` -> `Customers/CustomerDetails.tsx`.
- Detail labels: Name, Code, Mobile, Email, NID Number, Address, Status, Created Date.
- Sales table: Date, Vehicle Number, Quantity, Amount, Status.
- Payment table: SL, Voucher No, Date, Amount, Type, Payment Type, Status.
- Vehicle table: SL, Vehicle Number, Vehicle Name, Type, Product, Registration Date.
- SMS form: Customer, Phone Number, Message Type, Select SMS Template, Custom Message.
- `/customers/{customer}/statement` -> `Customers/CustomerStatement.tsx`.
- Statement summary: Name, Address, Account Number, Security Deposit, Current Balance.
- Statement tables: Month, Total Sale; Date, Amount, Payment Type, Remark.
- `/customer-ledger-summary` -> `CustomerLedgerSummary/Index.tsx`; hidden summary report; filters Customer, Start Date, End Date; columns Customer Name, Mobile, Address, Debit, Credit, Due; PDF.
- `/customer-ledger-details/{customer}` -> `CustomerLedgerDetails/Index.tsx`; hidden detail report; filters Start Date, End Date; columns SL, Date, Invoice No, Debit, Credit, Remarks; PDF.

#### Supplier

- `/suppliers/{supplier}` -> `Suppliers/SupplierDetails.tsx`.
- Detail labels: Name, Mobile, Email, Proprietor Name, Address, Status, Created Date.
- Purchase table: Date, Invoice No, Amount, Due, Status.
- Payment table: Date, Amount, Type, Status.
- `/suppliers/{supplier}/statement` -> `Suppliers/SupplierStatement.tsx`.
- Statement summary: Name, Address, Account Number, Current Balance.
- Purchase columns: SL, Date, Invoice No, Paid, Due, Total.
- Payment columns: SL, Date, Amount, Payment Type, Remark.

#### Employee

- `/employees/create` -> `Employee/Create.tsx`.
- `/employees/{employee}` -> `Employee/Show.tsx`.
- `/employees/{employee}/edit` -> `Employee/Update.tsx`.
- `/employees/{employee}/statement` -> `Employee/EmployeeStatement.tsx`.
- Show/statement transaction columns: SL, Voucher No, Date, Amount, Type, Payment Type/Sub Type, Status.
- Statement filters: Start Date, End Date.

#### Cash and Bank Ledger Details

- `/cash-book-ledger/{id}` -> `CashBookLedger/Show.tsx`.
- Columns: Date, Shift, From Account, To Account, Amount, Category, Transaction ID, Debit, Credit.
- `/bank-book-ledger/{ac_number}` -> `BankBookLedger/Show.tsx`.
- Filters: Start Date, End Date.
- Columns: Date, Shift, Voucher No, Description, Debit, Credit, Balance.

#### Loan Details

- `/loans/{account}` -> `loans/details.tsx`.
- Summary: Account Name, Account Number, Status, Created Date.
- Tables: SL, Voucher No, Date, Amount, Description.
- `/loans/{account}/statement` -> `loans/statement.tsx`.
- Summary: Account Name, Account Number, Total Loan, Total Payment, Current Balance.
- Loan/payment tables: SL, Voucher No, Date, Amount, Description.

#### Shift Closed Detail

- `/shift-closed-list/{id}` -> `ShiftClosedList/Show.tsx`.
- Dispenser columns: Dispenser, Product, Rate, Start, End, Test, Net, Total Sale, Employee.
- Other-product columns: Product, Code, Unit, Rate, Quantity, Total, Employee.

### Same-page Modal Edit/Data Routes

নিচের `edit` routes আলাদা React page render করে না; JSON দিয়ে list-page modal populate করে:

- `/credit-sales/{creditSale}/edit`
- `/purchases/{purchase}/edit`
- `/sales/{sale}/edit`
- `/payment-sub-types/{paymentSubType}/edit`
- `/roles/{role}/edit`
- `/users/{user}/edit`
- `/sms-configs/{smsConfig}/edit`

### Account Settings Pages

এগুলো business CRUD/sidebar inventory-এর বাইরে user-menu settings:

- `/settings/profile`: Name, Avatar, Email Address.
- `/settings/password`: Current Password, New Password, Confirm Password.
- `/settings/two-factor`: enable/disable, QR, confirmation, recovery codes.
- `/settings/appearance`: Light/Dark/System appearance control.

## 5. Public and Authentication Forms

Business CRUD নয়, কিন্তু project-complete page inventory হিসেবে:

- `/`: Home.
- `/about`: About.
- `/services`: Services.
- `/contact`: Contact.
- `/login`: Email Address, Password, Remember Me.
- `/register`: Full Name, Email Address, Password, Confirm Password.
- `/forgot-password`: Email Address.
- `/reset-password`: Email Address, New Password, Confirm New Password.
- `/confirm-password`: Password.
- `/two-factor-challenge`: Authentication Code অথবা Recovery Code.
- `/email/verify`: email-verification action screen.

## 6. PDF and Print Output Inventory

### Master/List Exports

- Accounts
- Categories
- Company Settings
- Credit Sales
- Customers
- Dispensers (route exists; template missing)
- Employee Departments
- Employee Designations
- Employee Types
- Employees (route exists; template missing)
- Groups
- Office Payments
- Payment Vouchers
- Permissions
- Product Rates
- Products
- Purchases
- Received Vouchers
- Roles
- Sales
- Shift Closed List
- Shifts
- SMS Configs
- SMS Templates
- Stocks (route exists; template missing)
- Stock Report
- Suppliers
- Units
- Users
- Vehicles
- Payment Sub Types (route exists; template missing)

### Report Exports

- Balance Sheet
- Bank Book Ledger
- Bank Book Ledger Account
- Cash Book Ledger
- Cash Book Shift
- Customer Details Bill
- Customer Ledger Details
- Customer Ledger Summary
- Customer Sales Reports
- Customer Summary Bill
- Daily Statement
- General Ledger
- Liability and Assets
- Monthly Dispenser Report
- Purchase Report Details

### Detail, Statement and Invoice Exports

- Batch Invoice
- Sale Batch
- Sale Invoice
- White Sale Invoice
- Customer Sales
- Customer Payments
- Supplier Purchases
- Supplier Payments
- Employee Advance
- Employee Payments
- Employee Receipts
- Employee Salary
- Loan Summary
- Loan Payments
- Loan Statement
- Shift Closed Show

## 7. Route and Implementation Findings

### Broken or Unimplemented Declared Page Routes

1. `accounts/create`, `accounts/{account}`, এবং `accounts/{account}/edit` route declared, কিন্তু `AccountController`-এ `create`, `show`, `edit` method নেই। বর্তমান usable account CRUD হলো `/accounts` list-page modal.
2. `white-sales/create`, `white-sales/{whiteSale}`, `white-sales/{whiteSale}/edit`, এবং `white-sales/download-pdf` route declared, কিন্তু `WhiteSaleController`-এ `create`, `show`, `edit`, `downloadPdf` method নেই। বর্তমান usable UI হলো `/white-sales` index page এবং single-invoice PDF.
3. `sms-templates/{smsTemplate}/edit` route declared, কিন্তু `SMSTemplateController`-এ `edit` method নেই। বর্তমান UI list-page modal ব্যবহার করে।

### Missing and Orphan PDF Templates

Controller-এর static `Pdf::loadView('pdf.*')` references actual Blade files-এর সঙ্গে compare করে পাওয়া গেছে:

1. `pdf.dispensers` referenced, কিন্তু `resources/views/pdf/dispensers.blade.php` নেই।
2. `pdf.employees` referenced, কিন্তু `resources/views/pdf/employees.blade.php` নেই।
3. `pdf.payment-sub-types` referenced, কিন্তু `resources/views/pdf/payment-sub-types.blade.php` নেই।
4. `pdf.stocks` referenced, কিন্তু `resources/views/pdf/stocks.blade.php` নেই।
5. `employee-advance.blade.php`, `employee-salary.blade.php`, `sale-batch.blade.php`, এবং `sale-invoice.blade.php` কোনো current static controller PDF reference-এ পাওয়া যায়নি; এগুলো legacy/orphan অথবা dynamic use কিনা verify করা দরকার।

### Navigation Gaps

1. `/payment-sub-types` একটি complete CRUD + PDF page, কিন্তু app sidebar-এ link নেই।
2. `/customer-ledger-summary` report page sidebar-এ নেই।
3. `/customer-ledger-details/{customer}` শুধু contextual/dynamic route; sidebar-এ থাকা সম্ভব নয়, কিন্তু customer navigation থেকে discoverability verify করা দরকার।

### Contract and Consistency Notes

1. Product Rate bulk delete `POST /product-rates/bulk-delete`; project-এর অধিকাংশ bulk delete route `DELETE .../bulk/delete`.
2. Accounts-এর create flow account number auto-generate করে, কিন্তু update form account number editable.
3. Roles table-এ Description column আছে, কিন্তু current role store/update validation শুধু Name এবং Permissions গ্রহণ করে।
4. Company Setting form অনেক field submit করে, কিন্তু controller validation বর্তমানে শুধু `company_name` validate করে এবং তারপর full request payload persist করে।
5. Customer form/UI-তে core fields দেখা যায়; backend আরও VAT Registration, TIN, Trade License, Discount Rate এবং Credit Limit গ্রহণ করে।
6. Permission form visible field হিসেবে Permission Name দেখায়; backend `guard_name`-ও গ্রহণ করে।

## 8. Coverage Matrix

| Area              |   Sidebar |             CRUD/Form |  Table/Report |                 Detail Page |                  PDF |
| ----------------- | --------: | --------------------: | ------------: | --------------------------: | -------------------: |
| General Setting   |       Yes |                   Yes |           Yes |                         Yes |                  Yes |
| Dispenser         |       Yes |                   Yes |           Yes |                         Yes |                  Yes |
| Customer          |       Yes |                   Yes |           Yes |                         Yes |                  Yes |
| Supplier          |       Yes |                   Yes |           Yes |                         Yes |                  Yes |
| Products/Stock    |       Yes |                   Yes |           Yes | No dedicated product detail |                  Yes |
| Purchase/Sales    |       Yes |                   Yes |           Yes |             Modal/JSON edit |                  Yes |
| Accounts/Vouchers |       Yes |                   Yes |           Yes |               Ledger detail |                  Yes |
| Loans             |       Yes |   No direct loan CRUD |           Yes |                         Yes |                  Yes |
| Employee          |       Yes |                   Yes |           Yes |                         Yes |                  Yes |
| User Management   |       Yes |                   Yes |           Yes |             Modal/JSON edit |                  Yes |
| SMS               |       Yes |      Partial for logs |           Yes |             Modal/JSON edit | Config/template only |
| Payment Sub Types |        No |                   Yes |           Yes |             Modal/JSON edit |                  Yes |
| User Settings     | User menu | Profile/password only | No list table |                          No |                   No |

## 9. Recommended QA Checklist

1. প্রত্যেক sidebar permission combination দিয়ে link visibility test করা।
2. প্রতি CRUD page-এ create, validation error, edit, single delete, bulk delete এবং PDF filter carry-over test করা।
3. Conditional payment methods: Cash, Bank/Cheque, Mobile Banking আলাদা করে test করা।
4. Batch forms: Sales, Credit Sales, Purchases, Received Voucher, Payment Voucher-এর add/remove line এবং total calculation test করা।
5. Dispenser closing-এ previous reading, net reading, other-product stock এবং settlement total cross-check করা।
6. Hidden page `/payment-sub-types`-এর intended navigation location ঠিক করা।
7. তিন সেট missing controller route method remove অথবা implement করা।
8. List/report PDF-এর columns on-screen table-এর সঙ্গে মিলিয়ে regression test করা।
