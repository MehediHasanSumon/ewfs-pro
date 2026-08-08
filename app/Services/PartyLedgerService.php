<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartyLedgerService
{
    public function __construct(
        private readonly CustomerSecurityDepositService $securityDeposits
    ) {}

    public function customerMetrics(
        Collection $customers,
        ?string $asOfDate = null
    ): Collection {
        $accountMap = $customers->pluck('id', 'account_id');
        $depositBalances = $this->securityDeposits
            ->balancesByAccountIds($accountMap->keys(), $asOfDate);
        $customerCategoryId = $this->customerCategoryId();
        $duePaidCode = VoucherTransactionTypeHelper::customerDuePaidCode();
        $legacyAdvancePaymentCode = VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode();
        $advanceReturnCode = VoucherTransactionTypeHelper::customerAdvanceReturnCode();
        $hasVoucherTypes = Schema::hasTable('vouchers')
            && Schema::hasTable('voucher_transaction_types');
        $activityQuery = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $accountMap->keys())
            ->whereIn('je.status', ['posted', 'reversed'])
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('je.business_date', '<=', $asOfDate))
            ->groupBy('jl.account_id');

        if ($hasVoucherTypes) {
            $activityQuery
                ->leftJoin('vouchers as v', function ($join) {
                    $join->on('v.id', '=', 'je.source_id')
                        ->where('je.source_type', Voucher::class);
                })
                ->leftJoin(
                    'voucher_transaction_types as vtt',
                    'vtt.id',
                    '=',
                    'v.voucher_transaction_type_id'
                );
        }

        $typedReceipt = $hasVoucherTypes
            ? '(vtt.voucher_category_id = ? AND vtt.code IN (?, ?)
                AND vtt.voucher_type = ?)'
            : '1 = 1';
        $typedReturn = $hasVoucherTypes
            ? '(vtt.voucher_category_id = ? AND vtt.code = ?
                AND vtt.voucher_type = ?)'
            : '1 = 0';
        $legacyReceipt = $hasVoucherTypes
            ? "(vtt.id IS NULL OR {$typedReceipt})"
            : '1 = 1';
        $bindings = $hasVoucherTypes
            ? [
                $customerCategoryId ?? 0,
                $duePaidCode,
                $legacyAdvancePaymentCode,
                VoucherTransactionTypeHelper::receiptVoucherType(),
                $customerCategoryId ?? 0,
                $advanceReturnCode,
                VoucherTransactionTypeHelper::paymentVoucherType(),
            ]
            : [];

        $activity = $activityQuery->selectRaw(
            "jl.account_id,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'credit_sale%'
                            THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                 ) AS total_sales,
                 COUNT(
                    DISTINCT CASE
                        WHEN je.event_type = 'credit_sale'
                            AND je.status = 'posted'
                            THEN je.id
                        ELSE NULL
                    END
                 ) AS sales_count,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'receipt_voucher%'
                            AND {$legacyReceipt}
                            THEN jl.credit_amount - jl.debit_amount
                        ELSE 0
                    END
                 ) AS total_received,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'payment_voucher%'
                            AND {$typedReturn}
                            THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                 ) AS advance_return,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'customer_opening_balance%'
                            AND LOWER(COALESCE(je.description, ''))
                                LIKE '%receivable%'
                            THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                 ) AS previous_due,
                 0 AS current_due",
            $bindings
        )
            ->get()
            ->keyBy('account_id');

        return $customers->mapWithKeys(function (Customer $customer) use (
            $activity,
            $depositBalances
        ) {
            $accountActivity = $activity->get($customer->account_id);
            $totalSales = (float) ($accountActivity->total_sales ?? 0);
            $totalReceived = (float) ($accountActivity->total_received ?? 0);
            $advanceReturn = (float) ($accountActivity->advance_return ?? 0);
            $previousDue = (float) ($accountActivity->previous_due ?? 0);
            $totalPaid = $totalReceived - $advanceReturn;
            $currentBalance = $totalSales - $totalPaid;

            return [$customer->id => [
                'total_sales' => $totalSales,
                'sales_count' => (int) ($accountActivity->sales_count ?? 0),
                'total_paid' => $totalPaid,
                'current_balance' => $currentBalance,
                'current_due' => max(0.0, $currentBalance),
                'current_advance' => max(0.0, -$currentBalance),
                'previous_due' => $previousDue,
                'total_received' => $totalReceived,
                'advance_return' => $advanceReturn,
                'security_deposit' => (float) $depositBalances
                    ->get($customer->account_id, 0),
            ]];
        });
    }

    public function customerCurrentDue(
        int $customerId,
        ?string $asOfDate = null
    ): float {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return 0.0;
        }

        return (float) ($this->customerMetrics(
            collect([$customer]),
            $asOfDate
        )->get($customerId)['current_due'] ?? 0);
    }

    public function customerCurrentAdvance(
        int $customerId,
        ?string $asOfDate = null
    ): float {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return 0.0;
        }

        return (float) ($this->customerMetrics(
            collect([$customer]),
            $asOfDate
        )->get($customerId)['current_advance'] ?? 0);
    }

    public function customerPosition(?string $asOfDate = null): array
    {
        $metrics = $this->customerMetrics(
            Customer::query()->get(['id', 'account_id']),
            $asOfDate
        );

        return [
            'due' => (float) $metrics->sum('current_due'),
            'advance' => (float) $metrics->sum('current_advance'),
            'security' => (float) $metrics->sum('security_deposit'),
        ];
    }

    public function customerPaymentCount(int $customerId): int
    {
        return $this->customerReceiptQuery($customerId)->count();
    }

    public function employeeFinancialMetrics(
        Collection $employees,
        ?string $asOfDate = null
    ): Collection {
        $employeeIds = $employees->pluck('id')->map(fn ($id): int => (int) $id);

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $employeeCategory = VoucherCategoryHelper::employeeCode();
        $monthlySalary = VoucherTransactionTypeHelper::monthlySalaryCode();
        $salaryAdvance = VoucherTransactionTypeHelper::employeeSalaryAdvanceCode();
        $personalLoan = VoucherTransactionTypeHelper::employeePersonalLoanCode();
        $advanceReturn = VoucherTransactionTypeHelper::employeeAdvanceReturnCode();
        $loanRecovery = VoucherTransactionTypeHelper::employeeLoanRecoveryCode();

        $activity = DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->leftJoin(
                'voucher_transaction_types as vtt',
                'vtt.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->leftJoin(
                'voucher_categories as vc',
                'vc.id',
                '=',
                'vtt.voucher_category_id'
            )
            ->whereIn('vl.employee_id', $employeeIds)
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('v.voucher_date', '<=', $asOfDate))
            ->groupBy('vl.employee_id')
            ->selectRaw(
                'vl.employee_id,
                 SUM(CASE WHEN v.voucher_type = ? AND vc.code = ? AND vtt.code = ? THEN vl.amount ELSE 0 END) AS monthly_salary_paid,
                 SUM(CASE WHEN v.voucher_type = ? AND vc.code = ? AND vtt.code = ? THEN vl.amount ELSE 0 END) AS salary_advance,
                 SUM(CASE WHEN v.voucher_type = ? AND vc.code = ? AND vtt.code = ? THEN vl.amount ELSE 0 END) AS personal_loan,
                 SUM(CASE WHEN v.voucher_type = ? AND vc.code = ? AND vtt.code = ? THEN vl.amount ELSE 0 END) AS advance_return,
                 SUM(CASE WHEN v.voucher_type = ? AND vc.code = ? AND vtt.code = ? THEN vl.amount ELSE 0 END) AS loan_recovery',
                [
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    $employeeCategory,
                    $monthlySalary,
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    $employeeCategory,
                    $salaryAdvance,
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    $employeeCategory,
                    $personalLoan,
                    VoucherTransactionTypeHelper::receiptVoucherType(),
                    $employeeCategory,
                    $advanceReturn,
                    VoucherTransactionTypeHelper::receiptVoucherType(),
                    $employeeCategory,
                    $loanRecovery,
                ]
            )
            ->get()
            ->keyBy('employee_id');

        $legacyAppliedAdvances = PayrollItem::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', PayrollItem::STATUS_PAID)
            ->whereNull('advance_adjustment_voucher_id')
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('processed_at', '<=', $asOfDate))
            ->groupBy('employee_id')
            ->select('employee_id')
            ->selectRaw('SUM(advance_applied) AS applied_advances')
            ->pluck('applied_advances', 'employee_id');

        $salaryDue = PayrollItem::query()
            ->join('payroll_periods as pp', 'pp.id', '=', 'payroll_items.payroll_period_id')
            ->whereIn('payroll_items.employee_id', $employeeIds)
            ->whereIn('pp.status', [
                PayrollPeriod::STATUS_GENERATED,
                PayrollPeriod::STATUS_PAID,
            ])
            ->where('payroll_items.status', PayrollItem::STATUS_PENDING)
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('pp.payable_date', '<=', $asOfDate))
            ->groupBy('payroll_items.employee_id')
            ->select('payroll_items.employee_id')
            ->selectRaw('SUM(payroll_items.net_payable) AS salary_due')
            ->pluck('salary_due', 'payroll_items.employee_id');

        return $employees->mapWithKeys(function (Employee $employee) use (
            $activity,
            $legacyAppliedAdvances,
            $salaryDue
        ) {
            $row = $activity->get($employee->id);
            $salaryAdvance = (float) ($row->salary_advance ?? 0);
            $advanceReturn = (float) ($row->advance_return ?? 0);
            $advanceApplied = (float) ($legacyAppliedAdvances->get($employee->id, 0));
            $loan = (float) ($row->personal_loan ?? 0);
            $loanRecovery = (float) ($row->loan_recovery ?? 0);

            return [$employee->id => [
                'monthly_salary' => (float) ($employee->salaryStructure?->net_salary
                    ?? $employee->salaryStructure?->gross_salary
                    ?? $employee->salary
                    ?? 0),
                'paid_salary' => (float) ($row->monthly_salary_paid ?? 0),
                'salary_due' => (float) ($salaryDue->get($employee->id, 0)),
                'salary_advance' => $salaryAdvance,
                'advance_return' => $advanceReturn,
                'advance_applied' => $advanceApplied,
                'net_advance' => max(0.0, $salaryAdvance - $advanceReturn - $advanceApplied),
                'personal_loan' => $loan,
                'loan_recovery' => $loanRecovery,
                'loan_balance' => max(0.0, $loan - $loanRecovery),
            ]];
        });
    }

    public function employeeFinancialMetric(
        Employee $employee,
        ?string $asOfDate = null
    ): array {
        return $this->employeeFinancialMetrics(
            collect([$employee->loadMissing('salaryStructure')]),
            $asOfDate
        )->get($employee->id, []);
    }

    public function customerPayments(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $limit = null
    ): Collection {
        return $this->customerPaymentQuery(
            $customerId,
            $startDate,
            $endDate
        )
            ->orderByDesc('date')
            ->orderByDesc('source_id')
            ->when($limit, fn (QueryBuilder $query) => $query->limit($limit))
            ->get()
            ->map(fn (object $row) => $this->normalizeCustomerPayment($row));
    }

    public function paginatedCustomerPayments(
        int $customerId,
        int $perPage,
        ?string $startDate = null,
        ?string $endDate = null
    ): LengthAwarePaginator {
        $paginator = $this->customerPaymentQuery(
            $customerId,
            $startDate,
            $endDate
        )
            ->orderByDesc('date')
            ->orderByDesc('source_id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (object $row) => $this->normalizeCustomerPayment($row))
        );

        return $paginator;
    }

    public function supplierMetrics(Collection $suppliers): Collection
    {
        $supplierIds = $suppliers->pluck('id');
        $accountMap = $suppliers->pluck('id', 'account_id');
        $activity = $this->accountActivity($accountMap->keys());

        $purchases = DB::table('purchases')
            ->whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->groupBy('supplier_id')
            ->selectRaw(
                'supplier_id, SUM(grand_total) AS total_purchases, COUNT(*) AS purchase_count'
            )
            ->get()
            ->keyBy('supplier_id');

        $payments = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('suppliers as s', function ($join) {
                $join->on('s.id', '=', 'jl.supplier_id')
                    ->on('s.account_id', '=', 'jl.account_id');
            })
            ->whereIn('s.id', $supplierIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereIn('je.event_type', [
                'purchase',
                'purchase_reversal',
                'payment_voucher',
                'payment_voucher_reversal',
            ])
            ->groupBy('s.id')
            ->selectRaw(
                "s.id AS supplier_id,
                 SUM(
                    CASE
                        WHEN je.event_type = 'purchase'
                            THEN jl.debit_amount
                        WHEN je.event_type = 'purchase_reversal'
                            THEN -jl.credit_amount
                        ELSE jl.debit_amount - jl.credit_amount
                    END
                 ) AS total_paid"
            )
            ->pluck('total_paid', 'supplier_id');

        return $suppliers->mapWithKeys(function (Supplier $supplier) use (
            $activity,
            $purchases,
            $payments
        ) {
            $accountActivity = $activity->get($supplier->account_id);
            $purchase = $purchases->get($supplier->id);

            return [$supplier->id => [
                'total_purchases' => (float) ($purchase->total_purchases ?? 0),
                'purchase_count' => (int) ($purchase->purchase_count ?? 0),
                'total_paid' => (float) ($payments->get($supplier->id) ?? 0),
                'current_due' => (float) ($accountActivity->total_credit ?? 0)
                    - (float) ($accountActivity->total_debit ?? 0),
            ]];
        });
    }

    public function statement(Account $account, string $perspective): Collection
    {
        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('vouchers as v', function ($join) {
                $join->on('v.id', '=', 'je.source_id')
                    ->where('je.source_type', Voucher::class);
            })
            ->leftJoin(
                'voucher_transaction_types as vtt',
                'vtt.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->where('jl.account_id', $account->id)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id')
            ->select([
                'jl.id',
                'je.business_date as date',
                'je.event_type',
                'je.reference_no',
                'je.description as entry_description',
                'jl.description',
                'jl.debit_amount',
                'jl.credit_amount',
                'v.voucher_no',
                'vtt.code as transaction_type_code',
                'vtt.name as transaction_type_name',
                'vtt.voucher_category_id as transaction_category_id',
            ])
            ->get();

        $balance = 0.0;

        return $rows->map(function (object $row) use ($perspective, &$balance) {
            $journalDebit = (float) $row->debit_amount;
            $journalCredit = (float) $row->credit_amount;
            $debit = $perspective === 'supplier' ? $journalCredit : $journalDebit;
            $credit = $perspective === 'supplier' ? $journalDebit : $journalCredit;

            $balance += $perspective === 'customer'
                ? $this->customerDueDelta($row, $debit, $credit)
                : $debit - $credit;

            return [
                'id' => $row->id,
                'date' => $row->date,
                'type' => $row->transaction_type_name
                    ?? str($row->event_type)->headline()->toString(),
                'transaction_type_code' => $row->transaction_type_code,
                'transaction_type_name' => $row->transaction_type_name,
                'description' => $row->description ?? $row->entry_description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'invoice_no' => $row->reference_no,
                'voucher_no' => $row->voucher_no,
            ];
        });
    }

    public function vouchers(
        string $partyColumn,
        int $partyId,
        string $voucherType,
        ?string $startDate = null,
        ?string $endDate = null
    ): Builder {
        return Voucher::query()
            ->posted()
            ->ofType($voucherType)
            ->whereHas('journalEntry', fn (Builder $entry) => $entry
                ->where('status', 'posted'))
            ->whereHas('lines', fn (Builder $lines) => $lines
                ->where($partyColumn, $partyId))
            ->with([
                'lines.account:id,name',
                'lines.paymentDetail',
                'voucherTransactionType:id,name,code',
                'voucherCategory:id,name',
            ])
            ->when($startDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '<=', $endDate));
    }

    public function voucherRows(Collection $vouchers, string $status): Collection
    {
        return $vouchers->map(fn (Voucher $voucher) => [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date' => $voucher->voucher_date->format('Y-m-d'),
            'amount' => (float) $voucher->amount,
            'payment_type' => $voucher->payment_method,
            'type' => $voucher->payment_method,
            'sub_type' => $voucher->voucherTransactionType?->name,
            'sub_type_name' => $voucher->voucherTransactionType?->name,
            'description' => $voucher->description,
            'remarks' => $voucher->remarks,
            'status' => $status,
        ]);
    }

    public function paginatedVoucherRows(
        Builder $query,
        int $perPage,
        string $status
    ): LengthAwarePaginator {
        $paginator = $query
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $this->voucherRows($paginator->getCollection(), $status)
        );

        return $paginator;
    }

    public function customerMonthlySales(int $customerId, int $year): Collection
    {
        return DB::table('credit_sale_customers as csc')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->where('csc.customer_id', $customerId)
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->whereYear('cs.sale_date', $year)
            ->groupByRaw('YEAR(cs.sale_date), MONTH(cs.sale_date)')
            ->orderByRaw('MONTH(cs.sale_date)')
            ->selectRaw(
                'YEAR(cs.sale_date) AS year,
                 MONTH(cs.sale_date) AS month,
                 SUM(csc.grand_total) AS total'
            )
            ->get()
            ->map(fn (object $sale) => [
                'month' => date('F Y', mktime(0, 0, 0, $sale->month, 1, $sale->year)),
                'total' => (float) $sale->total,
            ]);
    }

    public function customerSalesYears(int $customerId): array
    {
        return DB::table('credit_sale_customers as csc')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->where('csc.customer_id', $customerId)
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw('DISTINCT YEAR(cs.sale_date) AS year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    private function accountActivity(Collection $accountIds): Collection
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $accountIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->groupBy('jl.account_id')
            ->selectRaw(
                'jl.account_id,
                 SUM(jl.debit_amount) AS total_debit,
                 SUM(jl.credit_amount) AS total_credit'
            )
            ->get()
            ->keyBy('account_id');
    }

    private function customerPaymentQuery(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): QueryBuilder {
        return DB::query()->fromSub(
            $this->customerReceiptQuery($customerId, $startDate, $endDate)
                ->unionAll(
                    $this->customerDepositQuery(
                        $customerId,
                        $startDate,
                        $endDate
                    )
                )
                ->unionAll(
                    $this->customerRefundQuery(
                        $customerId,
                        $startDate,
                        $endDate
                    )
                )
                ->unionAll(
                    $this->customerAdvanceReturnQuery(
                        $customerId,
                        $startDate,
                        $endDate
                    )
                ),
            'customer_payments'
        );
    }

    private function customerReceiptQuery(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): QueryBuilder {
        return DB::table('vouchers as v')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->join('voucher_lines as customer_line', function ($join) use ($customerId) {
                $join->on('customer_line.voucher_id', '=', 'v.id')
                    ->where('customer_line.customer_id', $customerId)
                    ->where('customer_line.entry_side', 'credit');
            })
            ->leftJoin('voucher_lines as payment_line', function ($join) {
                $join->on('payment_line.voucher_id', '=', 'v.id')
                    ->where('payment_line.entry_side', 'debit');
            })
            ->leftJoin(
                'voucher_payment_details as payment',
                'payment.voucher_line_id',
                '=',
                'payment_line.id'
            )
            ->leftJoin(
                'voucher_transaction_types as subtype',
                'subtype.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->where('v.voucher_type', 'receipt')
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->when($startDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '>=', $startDate))
            ->when($endDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '<=', $endDate))
            ->selectRaw(
                "'voucher' AS source_type,
                 v.id AS source_id,
                 v.voucher_no,
                 v.voucher_date AS date,
                 customer_line.amount,
                 COALESCE(payment.payment_method, 'Cash') AS payment_type,
                 COALESCE(subtype.name, 'Receipt') AS sub_type,
                 COALESCE(payment.payment_method, 'Cash') AS type,
                 subtype.code AS transaction_type_code,
                 COALESCE(v.remarks, v.description, customer_line.description)
                    AS remarks,
                 'Received' AS status"
            );
    }

    private function customerDepositQuery(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): QueryBuilder {
        return DB::table('party_opening_balances as balance')
            ->join('journal_entries as je', 'je.id', '=', 'balance.journal_entry_id')
            ->where('balance.customer_id', $customerId)
            ->where('balance.balance_type', 'customer_deposit')
            ->where('balance.status', 'posted')
            ->where('je.status', 'posted')
            ->when($startDate, fn (QueryBuilder $query) => $query
                ->whereDate('balance.effective_date', '>=', $startDate))
            ->when($endDate, fn (QueryBuilder $query) => $query
                ->whereDate('balance.effective_date', '<=', $endDate))
            ->selectRaw(
                "'security_deposit' AS source_type,
                 balance.id AS source_id,
                 COALESCE(je.reference_no, 'N/A') AS voucher_no,
                 balance.effective_date AS date,
                 balance.amount,
                 'Security Deposit' AS payment_type,
                 'Security Deposit' AS sub_type,
                 'Security Deposit' AS type,
                 NULL AS transaction_type_code,
                 COALESCE(je.description, 'Opening Security Deposit') AS remarks,
                 'Completed' AS status"
            );
    }

    private function customerRefundQuery(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): QueryBuilder {
        return DB::table('vouchers as v')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->join('voucher_transaction_types as subtype', function ($join) {
                $join->on('subtype.id', '=', 'v.voucher_transaction_type_id')
                    ->where(
                        'subtype.code',
                        VoucherTransactionTypeHelper::customerSecurityDepositRefundCode()
                    );
            })
            ->join('voucher_lines as customer_line', function ($join) use ($customerId) {
                $join->on('customer_line.voucher_id', '=', 'v.id')
                    ->where('customer_line.customer_id', $customerId)
                    ->where('customer_line.entry_side', 'debit');
            })
            ->leftJoin(
                'voucher_payment_details as payment',
                'payment.voucher_line_id',
                '=',
                'customer_line.id'
            )
            ->where('v.voucher_type', 'payment')
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->when($startDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '>=', $startDate))
            ->when($endDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '<=', $endDate))
            ->selectRaw(
                "'security_deposit_refund' AS source_type,
                 v.id AS source_id,
                 v.voucher_no,
                 v.voucher_date AS date,
                 customer_line.amount,
                 COALESCE(payment.payment_method, 'Cash') AS payment_type,
                 subtype.name AS sub_type,
                 subtype.name AS type,
                 subtype.code AS transaction_type_code,
                 COALESCE(v.remarks, v.description, customer_line.description)
                    AS remarks,
                 'Completed' AS status"
            );
    }

    private function customerAdvanceReturnQuery(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): QueryBuilder {
        return DB::table('vouchers as v')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->join('voucher_transaction_types as subtype', function ($join) {
                $join->on('subtype.id', '=', 'v.voucher_transaction_type_id')
                    ->where(
                        'subtype.code',
                        VoucherTransactionTypeHelper::customerAdvanceReturnCode()
                    );
            })
            ->join('voucher_lines as customer_line', function ($join) use ($customerId) {
                $join->on('customer_line.voucher_id', '=', 'v.id')
                    ->where('customer_line.customer_id', $customerId)
                    ->where('customer_line.entry_side', 'debit');
            })
            ->leftJoin(
                'voucher_payment_details as payment',
                'payment.voucher_line_id',
                '=',
                'customer_line.id'
            )
            ->where('v.voucher_type', 'payment')
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->when($startDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '>=', $startDate))
            ->when($endDate, fn (QueryBuilder $query) => $query
                ->whereDate('v.voucher_date', '<=', $endDate))
            ->selectRaw(
                "'advance_return' AS source_type,
                 v.id AS source_id,
                 v.voucher_no,
                 v.voucher_date AS date,
                 customer_line.amount,
                 COALESCE(payment.payment_method, 'Cash') AS payment_type,
                 subtype.name AS sub_type,
                 subtype.name AS type,
                 subtype.code AS transaction_type_code,
                 COALESCE(v.remarks, v.description, customer_line.description)
                    AS remarks,
                 'Completed' AS status"
            );
    }

    private function normalizeCustomerPayment(object $row): array
    {
        return [
            'id' => (int) $row->source_id,
            'key' => $row->source_type.'-'.$row->source_id,
            'source_type' => $row->source_type,
            'voucher_no' => $row->voucher_no,
            'date' => $row->date,
            'amount' => (float) $row->amount,
            'payment_type' => $row->payment_type,
            'type' => $row->type,
            'sub_type' => $row->sub_type,
            'sub_type_name' => $row->sub_type,
            'transaction_type_code' => $row->transaction_type_code ?? null,
            'description' => $row->remarks,
            'remarks' => $row->remarks,
            'status' => $row->status,
        ];
    }

    private function customerDueDelta(
        object $row,
        float $debit,
        float $credit
    ): float {
        if (str_starts_with($row->event_type, 'credit_sale')) {
            return $debit - $credit;
        }

        if (
            str_starts_with($row->event_type, 'receipt_voucher')
            && (
                $row->transaction_type_code === null
                || (
                    (int) ($row->transaction_category_id ?? 0)
                        === (int) ($this->customerCategoryId() ?? -1)
                    && in_array($row->transaction_type_code, [
                        VoucherTransactionTypeHelper::customerDuePaidCode(),
                        VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode(),
                    ], true)
                )
            )
        ) {
            return $debit - $credit;
        }

        if (
            str_starts_with($row->event_type, 'payment_voucher')
            && (int) ($row->transaction_category_id ?? 0)
                === (int) ($this->customerCategoryId() ?? -1)
            && $row->transaction_type_code
                === VoucherTransactionTypeHelper::customerAdvanceReturnCode()
        ) {
            return $debit - $credit;
        }

        return 0.0;
    }

    private function customerCategoryId(): ?int
    {
        if (! Schema::hasTable('voucher_categories')) {
            return null;
        }

        $query = VoucherCategory::query();

        if (Schema::hasColumn('voucher_categories', 'code')) {
            $query->where('code', VoucherCategoryHelper::customerCode());
        } else {
            $query->where(
                'name',
                VoucherCategoryHelper::getCategoryDefaultName('customer')
            );
        }

        return $query->value('id');
    }
}
