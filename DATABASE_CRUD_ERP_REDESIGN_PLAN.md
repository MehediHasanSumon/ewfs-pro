# Database and CRUD ERP Redesign Plan

Prepared: July 27, 2026

Source references:

- `Reports/Daily Statement Report.xls`
- `Reports/Monthly Dispenser Report.xlsx`
- `Reports/Customer Details Bill.xls`
- `Reports/Customer Summary Bill.xls`
- `Reports/Balance Sheet.xlsx`
- Current migrations, models, controllers, CRUD pages, and audit findings

## Fixed Scope

1. Existing frontend forms থেকে কোনো input remove করা হবে না।
2. Existing frontend forms-এ নতুন input add করা হবে না।
3. Existing input-এর meaning, validation, posting rule, calculation, and persistence backend service layer-এ ঠিক করা হবে।
4. Financial value-এর source of truth হবে immutable double-entry journal; `accounts.total_amount`, `paid_amount`, `due_amount` source of truth থাকবে না।
5. Posted financial or inventory record hard delete হবে না; existing Delete action backend-এ reversal/void তৈরি করবে।
6. Excel files report layout এবং expected business output-এর contract হবে। Excel-এর broken/manual formula সরাসরি copy না করে accounting equation দিয়ে result তৈরি হবে।

## Target Accounting Rules

### Daily Statement

```text
Gross Product Sales
+ Cash Customer Collection
+ Other Income
+ Employee Advance Return
+ Driver Commission Receive
- Credit Sales
- Bank/Cheque/TT/Online Sales
- Local Purchase Paid
- Expense Paid
- Employee Advance Paid
- Driver Commission Paid
- Head Office/Office Transfer
= Expected Cash in Hand
```

### Monthly Dispenser

```text
Product Sales Amount
+ Previous Due Collection
- Current Credit/Due Sales
- POS/Bank Sales
- Expenditure
- Salary/Owner Payment
- Pay Order/Head Office Transfer
= Expected Shift Balance
```

### Customer Bills

- Details Bill: customer -> vehicle -> date/memo -> product, unit, quantity, unit price, line total.
- Summary Bill: customer -> vehicle -> product grouped quantity, slip count, unit price, total.
- Bill amount must come from posted sale items, not current product rate.

### Balance Sheet

```text
Assets = Liabilities + Equity
Net Profit = Revenue - Cost of Goods Sold - Expenses
Closing Equity = Opening Equity + Net Profit - Drawings
```

Balance Sheet কখনো account-name string, manually maintained account total, অথবা current product rate থেকে historical value হিসাব করবে না।

## Phase 1 - Canonical Chart of Accounts and Data Ownership

### Database work

- `accounts` table-কে master-data table হিসেবে রাখুন: account identity, account code, name, group, status.
- `groups.parents` string-এর বদলে canonical `parent_id` relationship প্রস্তুত করুন।
- `accounts.group_id`-কে canonical FK করুন; `group_code` transition period-এ compatibility field হিসেবে থাকবে।
- Account classification-এর জন্য stable semantic code রাখুন:
  - cash
  - bank
  - mobile bank
  - accounts receivable
  - accounts payable
  - customer deposit/advance
  - inventory
  - revenue
  - cost of goods sold
  - expense
  - salary
  - owner drawing
  - opening balance clearing
- Hardcoded group ID, group code, account name, এবং payment subtype code একটি central accounting mapping table/configuration-এ নিন।

### CRUD behavior

- Account CRUD-এর existing inputs একই থাকবে।
- `due_amount`, `paid_amount`, `total_amount` frontend payload-এ থাকলেও controller এগুলো financial posting হিসেবে গ্রহণ করবে না।
- Account number update করার আগে linked financial records-এর immutability নিশ্চিত করতে হবে।
- Customer, Supplier, Employee create/delete account lifecycle একটি database transaction-এর মধ্যে হবে।

### Acceptance

- একই account-এর group ID এবং group code conflict করতে পারবে না।
- orphan customer/supplier/employee account থাকবে না।
- report classification account name পরিবর্তনের পরও নষ্ট হবে না।

## Phase 2 - Immutable Journal and Unified Voucher Foundation

### New core tables

#### `journal_entries`

- `id` BIGINT
- `entry_no` unique
- `entry_date`, `entry_time`
- `shift_id` nullable
- `event_type`: sale, purchase, receipt, payment, office_payment, opening_balance, expense, income, stock_adjustment, shift_close
- `source_type`, `source_id`
- `reference_no`, `description`
- `status`: draft, posted, reversed
- `reversal_of_id` nullable
- `idempotency_key` unique
- `posted_by`, `posted_at`

#### `journal_lines`

- `id` BIGINT
- `journal_entry_id`
- `account_id`
- `debit_amount DECIMAL(24,4)`
- `credit_amount DECIMAL(24,4)`
- nullable reporting dimensions: customer, supplier, employee, product, vehicle, payment method
- indexes on account/date, entry/date, party/date, product/date, shift/date

#### `account_daily_balances`

- account/date opening debit, opening credit, period debit, period credit, closing balance
- এটি report/read optimization table; journal lines source of truth থাকবে।

### Voucher consolidation

- `vouchers`-কে common financial document table করুন।
- `voucher_type` values:
  - receipt
  - payment
  - office_payment
  - contra
  - opening_balance
- `amount`, `payment_method`, bank/mobile/cheque metadata, `journal_entry_id`, status সরাসরি voucher header-এ রাখুন।
- Existing `office_payments` data `vouchers`-এ `voucher_type = office_payment` হিসেবে migrate করুন।
- Transition period-এ `office_payments` compatibility view/model রাখা যাবে; সব report cutover-এর পরে legacy table archive হবে।
- Existing Office Payment, Payment Voucher, Received Voucher inputs/routes একই থাকবে; তিনটি controller একই `VoucherPostingService` ব্যবহার করবে।

### Posting rules

- Receipt: cash/bank account debit, payer/customer/other account credit.
- Payment: expense/supplier/other account debit, cash/bank account credit.
- Office payment/head-office send: destination/clearing account debit, source cash/bank account credit.
- Every entry-তে total debit = total credit বাধ্যতামূলক।
- Same source event দ্বিতীয়বার submit হলে `idempotency_key` duplicate posting বন্ধ করবে।

### Acceptance

- Voucher edit old entry mutate করবে না; old entry reverse করে corrected entry post করবে।
- Voucher delete existing button দিয়েই reversal হবে।
- Office payment এবং payment/received voucher একই ledger/report pipeline-এ দেখা যাবে।

## Phase 3 - Customer Previous Due, Advance, and Party Ledger

### Database work

- `party_opening_balances` table তৈরি করুন:
  - party type and party ID
  - balance type: receivable, payable, customer_deposit
  - amount
  - journal entry ID
  - unique party + balance type
- Customer-এর existing `previous_due` input:
  - Accounts Receivable debit
  - Opening Balance Clearing credit
- Existing `security_deposit` value:
  - Opening Balance Clearing debit
  - Customer Deposit Liability credit
- এটি cash receipt হিসেবে ধরা হবে না, কারণ existing customer form-এ cash/bank account input নেই।
- Future actual deposit receipt existing Received Voucher form দিয়ে post হবে।

### CRUD behavior

- Customer create, customer account, optional vehicle, opening due/deposit journal একই DB transaction-এ হবে।
- Customer update নতুন fake `credit_sales(type=previous)` append করবে না।
- Existing opening entry থাকলে reverse-and-replace হবে; amount zero হলে old opening entry reverse হবে।
- Customer current due:

```text
Opening Receivable
+ Credit Sale
- Receipt/Collection
- Credit Note/Return
= Current Receivable
```

- Customer advance:

```text
Opening Deposit
+ Advance Receipt
- Advance Applied to Invoice
- Advance Refund
= Customer Deposit Liability
```

### Supplier and employee

- Supplier payable এবং employee advance/salary একই party-ledger dimensions ব্যবহার করবে।
- Supplier/customer/employee profile hard delete না করে deactivate/soft delete করুন যদি posted journal থাকে।

### Acceptance

- Customer edit বারবার save করলে previous due duplicate হবে না।
- Customer statement, ledger summary, ledger details, advance এবং due একই journal থেকে reconcile করবে।
- Customer master-এর `current_due` বা account cached balance report source হবে না।

## Phase 4 - Sales, Credit Sales, White Sales, and Purchase Normalization

### Sales target model

#### `sales`

- one row per invoice
- sale type: regular, credit, white
- sale date/time, shift, invoice, memo
- nullable normalized customer and vehicle IDs
- customer/company/vehicle snapshot fields
- subtotal, discount, grand total, paid total, due total
- status: draft, posted, partially_paid, paid, void
- journal entry ID

#### `sale_items`

- sale ID, product ID, category/unit snapshot
- quantity, unit price, gross amount, discount, net amount
- purchase/cost price snapshot

#### `sale_payments`

- sale ID, voucher/journal reference, payment account, payment method, amount

### Existing CRUD mapping

- Existing regular Sale form-এর customer, vehicle, product, amount, paid amount, payment method inputs অপরিবর্তিত থাকবে।
- Existing Credit Sale form একই sales header/item tables-এ `sale_type = credit` লিখবে।
- Existing White Sale form একই tables-এ `sale_type = white` লিখবে এবং stock/ledger post করবে।
- Current batch cart একটি invoice header এবং multiple sale item তৈরি করবে।
- Product/customer/vehicle name দিয়ে lookup করা যাবে, কিন্তু persisted relation ID এবং snapshot দুইটাই রাখতে হবে।

### Sale calculations

```text
Line Gross = Quantity x Unit Price
Line Net = Line Gross - Line Discount
Invoice Total = Sum(Line Net)
Due = Invoice Total - Valid Posted Payments - Applied Advance
```

- `paid_amount > total` হলে excess customer advance হিসেবে post হবে অথবা validation fail হবে; silent overpayment নয়।
- Regular Sale controller আর `due_amount = 0` force করবে না।
- Historical bill current product rate দিয়ে recalculate হবে না।

### Purchase target model

- `purchases`: one row per supplier invoice/memo.
- `purchase_items`: product, quantity, unit cost, line total.
- `purchase_payments`: payment allocations.
- Supplier payable journal থেকে derive হবে।

### Acceptance

- Sale total, paid, due equation সব route-এ একই হবে।
- Credit sale আর separate accounting truth হবে না; এটি sale payment status হবে।
- White sale invoice, stock, revenue, and cash/bank posting reconcile করবে।
- Purchase update/delete stock ও ledger reversal ছাড়া direct overwrite করবে না।

## Phase 5 - Inventory Movement and Effective Product Rate

### New inventory tables

#### `inventory_movements`

- product ID
- movement date/time and shift
- movement type: opening, purchase, sale, credit_sale, white_sale, dispenser_other_sale, adjustment, reversal
- source type/source ID/source line ID
- quantity in, quantity out
- unit cost
- idempotency key and reversal reference

#### `stock_balances`

- product ID unique
- on hand, reserved, available
- version number for optimistic/row locking
- updated from inventory movements only

### CRUD behavior

- Existing Stock CRUD inputs থাকবে।
- Manual current/available stock change সরাসরি overwrite না করে `stock_adjustment` movement তৈরি করবে।
- Purchase positive movement তৈরি করবে।
- Sale/Credit/White/Other Product Sale negative movement তৈরি করবে।
- Update/delete reversal movement তৈরি করবে।
- Negative stock server-side transaction ও row lock দিয়ে block হবে।

### Product rates

- `(product_id, effective_date)` unique করুন।
- Rate lookup must use:

```text
status = active
AND effective_date <= transaction_date
ORDER BY effective_date DESC
```

- Future active rate transaction date-এর আগে current rate হবে না।
- Existing Product Rate inputs অপরিবর্তিত থাকবে।
- Sale/purchase item-এ selected rate snapshot persist হবে।

### Acceptance

- `stock_balances` = sum of all posted inventory movements.
- Current stock manually edit হলেও audit trail থাকবে।
- Same product/date-এ conflicting rate থাকবে না।

## Phase 6 - Server-Side Dispenser and Shift Closing

### Database work

- `shift_closings` table:
  - business date + shift unique
  - status: draft, posting, posted, reversed
  - expected cash, actual ledger cash, variance
  - journal entry ID
  - posted by/posted at
- `dispenser_readings`-এ unique closing + dispenser রাখুন।
- Start reading previous posted closing থেকে server নেবে।
- End reading এবং meter test existing input থেকে নেবে।
- Net reading and sale amount server calculate করবে।
- Other-product sale একই closing-এর child line এবং inventory movement হবে।

### Calculation

```text
Net Reading = End Reading - Start Reading - Meter Test
Product Amount = Net Reading x Effective Sale Rate
Cash Sale = Product Sales - Credit Sale - Bank/POS Sale
Expected Cash = Cash Sale + Cash Collection + Other Cash Income
                - Cash Payment - Office/Head Office Transfer
Variance = Expected Cash - Actual Cash Ledger Balance
```

### CRUD behavior

- Existing dispenser form inputs একই থাকবে।
- Frontend calculated `net_reading`, `total_sale`, `total_cash`, `final_due_amount` display-only hints হবে; server request values trust করবে না।
- Office Payment input/button unified voucher service-এ post হবে।
- Shift close submit idempotent এবং atomic হবে।
- Duplicate close database unique key এবং locking দিয়ে block হবে।

### Acceptance

- Dispenser liters x historical rate = posted sale amount.
- Shift close, voucher, stock, and journal partial state তৈরি করতে পারবে না।
- Monthly report-এর expected balance এবং ledger cash difference explainable হবে।

## Phase 7 - Excel-Compatible Reporting Read Models

### Reporting dimensions

- `report_bucket_mappings` table দিয়ে account/event/payment subtype-কে Excel sections-এ map করুন:
  - cash sale
  - credit sale
  - coupon cash collection
  - coupon bank collection
  - non-coupon collection
  - income
  - local purchase
  - expense
  - salary
  - employee advance/return
  - driver commission receive/payment
  - POS/bank sale
  - office/head-office transfer
  - pay order
- Customer reporting class existing data থেকে backfill হবে; form input add করা হবে না।

### Read models

- `daily_product_sales_summary`
- `daily_party_collection_summary`
- `daily_cash_reconciliation`
- `monthly_dispenser_summary`
- `customer_bill_detail`
- `customer_bill_summary`
- `account_monthly_balances`
- `inventory_monthly_valuation`

### Report contracts

- Daily Statement Excel-এর প্রতিটি section posted source event থেকে আসবে।
- Monthly Dispenser product columns dynamic হবে; LPG/Octane/Diesel hardcode করা হবে না।
- Customer Details Bill invoice item snapshots ব্যবহার করবে।
- Customer Summary Bill vehicle/product grouped quantity, slip count, amount দেবে।
- Balance Sheet trial balance থেকে তৈরি হবে:
  - assets and liabilities by account class
  - revenue and expenses by period
  - inventory valuation by movement cost
  - receivable/payable by party ledger

### Acceptance

- প্রতিটি report total drill-down করে source journal/sale/purchase/voucher/stock movement পর্যন্ত যাওয়া যাবে।
- Excel sample period-এর জন্য golden-master comparison থাকবে।
- Daily cash, monthly cash, general ledger, and balance sheet একই posting set থেকে reconcile করবে।

## Phase 8 - CRUD Reliability Without Changing Inputs

### Shared backend services

- `SalePostingService`
- `PurchasePostingService`
- `VoucherPostingService`
- `OpeningBalanceService`
- `InventoryPostingService`
- `ShiftClosingService`
- `ReversalService`

### Frontend logic corrections

- Create/edit modal open হলে current record দিয়ে form state rehydrate হবে।
- Validation error create এবং edit contract অনুযায়ী একই field-এ দেখাবে।
- Existing calculated fields server response থেকে refresh হবে।
- Paid/due/amount inputs-এর cross-field validation submit-এর আগে এবং server-এ হবে।
- Create/update/delete processing state সব form/modal-এ বাধ্যতামূলক হবে।
- Filter/page change হলে hidden selected IDs clear হবে; off-page bulk delete হবে না।
- Existing status, date, account, product, customer, vehicle, payment fields অপরিবর্তিত থাকবে।

### Delete behavior

- Master data unused হলে hard delete করা যেতে পারে।
- Financial/inventory history থাকলে deactivate বা reverse হবে।
- Voucher/sale/purchase/shift close delete action user-facing একই থাকবে, কিন্তু backend audit-preserving reversal করবে।

### Acceptance

- Double click duplicate invoice, voucher, stock movement, or shift close তৈরি করবে না।
- Edit operation old accounting effect reverse করে exactly one corrected effect তৈরি করবে।
- TypeScript and ESLint checks clean না হওয়া পর্যন্ত phase complete হবে না।

## Phase 9 - Migration, Reconciliation, and Billion-Row Scale

### Migration sequence

1. New tables and indexes deploy করুন; old tables untouched রাখুন।
2. Historical data batch-wise backfill করুন।
3. Old transaction groups থেকে balanced journal entries তৈরি করুন।
4. Office payments unified vouchers-এ migrate করুন।
5. Previous dues opening journals-এ migrate করুন; duplicate previous rows detect/merge করুন।
6. Sale/credit/white/purchase rows header-line model-এ migrate করুন।
7. Historical stock movement rebuild করে current stock reconcile করুন।
8. Dual-write চালু করে old/new result compare করুন।
9. Report read models new ledger থেকে চালিয়ে Excel golden-master compare করুন।
10. Reconciliation zero হলে read cutover; পরে old tables read-only/archive করুন।

### Scale requirements

- All high-volume IDs `BIGINT UNSIGNED`.
- Monetary columns `DECIMAL(24,4)`; quantity/rate domain অনুযায়ী `DECIMAL(24,6)`.
- Covering indexes:
  - journal lines: account + date + entry
  - journal entries: event + source, date + status, shift + date
  - sale/purchase items: product + date, party + date
  - inventory movements: product + date + source
  - vouchers: type + date + shift + account
- Monthly summary tables incremental update হবে; billion-row report raw tables scan করবে না।
- Hot transactional data এবং closed historical periods আলাদা retention/archive policy পাবে।
- Period close-এর পরে old journal entry mutate করা যাবে না।
- Queue/outbox দিয়ে report projection update করা যাবে, কিন্তু financial posting একই DB transaction-এ commit হবে।
- Backup restore, point-in-time recovery, and migration rollback rehearsal বাধ্যতামূলক।

### Required reconciliation checks

```text
Sum(Journal Debit) = Sum(Journal Credit)
Account Daily Closing = Previous Closing + Debit - Credit
Customer Due = Receivable Ledger Balance
Supplier Due = Payable Ledger Balance
Stock Balance = Sum(Inventory Movements)
Sale Total = Sum(Sale Items)
Purchase Total = Sum(Purchase Items)
Shift Expected Cash = Daily Statement Cash in Hand
Monthly Closing = Sum(Posted Shift Closings)
Balance Sheet Assets = Liabilities + Equity
```

## Implementation Priority

1. Phase 1-3 আগে করুন; ledger এবং opening balance ঠিক না করে report fix শুরু করবেন না।
2. Phase 4-6 transaction and stock correctness-এর জন্য দ্বিতীয় release।
3. Phase 7 Excel report replacement-এর জন্য তৃতীয় release।
4. Phase 8-9 production hardening, migration, scale, and cutover release।

## Definition of Done

- Existing form inputs unchanged.
- Every money movement has one balanced journal entry.
- Every stock change has one inventory movement.
- Every edit/delete has traceable reversal.
- Customer previous due/security deposit idempotent and ledger-backed.
- Office Payment, Payment Voucher, and Received Voucher unified.
- Daily Statement, Monthly Dispenser, Customer Bills, and Balance Sheet reconcile from the same source data.
- No report depends on manually edited account totals or current product rate for historical transactions.
