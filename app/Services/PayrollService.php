<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollSnapshot;
use App\Models\VoucherTransactionType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        private readonly VoucherPostingService $vouchers,
        private readonly PartyLedgerService $ledger,
        private readonly PaymentAccountService $paymentAccounts,
        private readonly SystemAccountService $systemAccounts
    ) {}

    public function createPeriod(array $data): PayrollPeriod
    {
        try {
            return PayrollPeriod::query()->create([
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
                'payable_date' => $data['payable_date'] ?? null,
                'status' => PayrollPeriod::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'month' => 'A payroll period already exists for this month and year.',
                ]);
            }

            throw $exception;
        }
    }

    public function start(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== PayrollPeriod::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'period' => 'Only draft payroll periods can be started.',
                ]);
            }

            $employees = Employee::query()
                ->where('status', true)
                ->with([
                    'salaryStructure',
                    'department:id,name',
                    'designation:id,name',
                    'paymentAccount.group:id,code,name,account_class,status',
                ])
                ->whereHas('salaryStructure')
                ->orderBy('employee_name')
                ->orderBy('id')
                ->get();

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages([
                    'period' => 'No active employees with salary structures are available.',
                ]);
            }

            foreach ($employees as $employee) {
                $this->snapshot($period, $employee);
            }

            $metrics = $this->ledger->employeeFinancialMetrics($employees);

            foreach ($employees as $employee) {
                $metric = $metrics->get($employee->id, []);
                $netSalary = (float) $employee->salaryStructure->gross_salary;
                $advanceBalance = max(0.0, (float) ($metric['net_advance'] ?? 0));
                $advanceApplied = min($netSalary, $advanceBalance);

                PayrollItem::query()
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->update([
                        'advance_balance' => $advanceBalance,
                        'advance_applied' => $advanceApplied,
                        'loan_balance' => max(0.0, (float) ($metric['loan_balance'] ?? 0)),
                        'net_payable' => round(max(0.0, $netSalary - $advanceApplied), 4),
                        'updated_at' => now(),
                    ]);
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_PROCESSING,
                'started_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            return $period->fresh(['snapshots', 'items']);
        }, 3);
    }

    public function process(
        PayrollPeriod $period,
        array $data
    ): Collection {
        return DB::transaction(function () use ($period, $data): Collection {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== PayrollPeriod::STATUS_PROCESSING) {
                throw ValidationException::withMessages([
                    'period' => 'Only processing payroll periods can be paid.',
                ]);
            }

            $employeeIds = collect($data['employee_ids'])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            Employee::query()
                ->whereIn('id', $employeeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $items = PayrollItem::query()
                ->where('payroll_period_id', $period->id)
                ->whereIn('employee_id', $employeeIds)
                ->pending()
                ->with([
                    'snapshot.paymentAccount.group',
                    'employee.account',
                    'snapshot.employee',
                ])
                ->lockForUpdate()
                ->get()
                ->sortBy('employee_id')
                ->values();

            if ($items->count() !== $employeeIds->count()) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'One or more selected payroll items are unavailable or already processed.',
                ]);
            }

            $employees = $items->pluck('employee')
                ->filter()
                ->values();
            $metrics = $this->ledger->employeeFinancialMetrics($employees);
            $monthlySalaryType = $this->employeeMonthlySalaryType();
            $advanceReturnType = null;
            $salaryExpenseAccount = null;
            $lines = [];
            $adjustmentLines = [];
            $prepared = [];

            foreach ($items as $item) {
                $employee = $item->employee;
                $snapshot = $item->snapshot;
                $metric = $metrics->get($employee->id, []);
                $advanceBalance = max(0.0, (float) ($metric['net_advance'] ?? 0));
                $advanceApplied = min(
                    (float) $snapshot->net_salary,
                    $advanceBalance
                );
                $netPayable = round(
                    max(0.0, (float) $snapshot->net_salary - $advanceApplied),
                    4
                );
                $paymentAccount = $snapshot->payment_account_id
                    ? $snapshot->paymentAccount
                    : null;
                $paymentMethod = $snapshot->payment_method;

                if ($netPayable > 0 && (! $paymentAccount || ! $paymentMethod)) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "{$snapshot->employee_name} has no valid payment account.",
                    ]);
                }

                $prepared[$item->id] = [
                    'item' => $item,
                    'advance_balance' => $advanceBalance,
                    'advance_applied' => $advanceApplied,
                    'net_payable' => $netPayable,
                ];

                if ($advanceApplied > 0) {
                    $advanceReturnType ??= $this->employeeAdvanceReturnType();
                    $salaryExpenseAccount ??= $this->systemAccounts
                        ->payrollSalaryExpense();
                    $adjustmentLines[] = [
                        'employee_item_id' => $item->id,
                        'voucher_category_id' => $advanceReturnType->voucher_category_id,
                        'voucher_transaction_type_id' => $advanceReturnType->id,
                        'from_account_id' => $employee->account_id,
                        'to_account_id' => $salaryExpenseAccount->id,
                        'amount' => $advanceApplied,
                        'payment_method' => 'journal',
                        'description' => "Salary advance adjustment for {$snapshot->employee_name} for {$period->label()}.",
                        'remarks' => "Salary advance adjustment for {$snapshot->employee_name} for {$period->label()}.",
                    ];
                }

                if ($netPayable > 0) {
                    $salaryExpenseAccount ??= $this->systemAccounts
                        ->payrollSalaryExpense();
                    $remarks = trim((string) (
                        $data['remarks'][$employee->id]
                        ?? "Monthly salary payment for {$snapshot->employee_name} for {$period->label()}."
                    ));

                    $lines[] = [
                        'employee_item_id' => $item->id,
                        'voucher_category_id' => $monthlySalaryType->voucher_category_id,
                        'voucher_transaction_type_id' => $monthlySalaryType->id,
                        'from_account_id' => $snapshot->payment_account_id,
                        'to_account_id' => $salaryExpenseAccount->id,
                        'employee_id' => $employee->id,
                        'amount' => $netPayable,
                        'payment_method' => $paymentMethod,
                        'description' => $remarks,
                        'remarks' => $remarks,
                    ];
                }
            }

            $adjustments = $adjustmentLines === []
                ? []
                : $this->vouchers->createMany(
                    VoucherTransactionTypeHelper::receiptVoucherType(),
                    [
                        'date' => $data['date'],
                        'vouchers' => $adjustmentLines,
                    ]
                );
            $posted = $lines === []
                ? []
                : $this->vouchers->createMany(
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    [
                        'date' => $data['date'],
                        'vouchers' => $lines,
                    ]
                );
            $postedByItem = collect($posted)->keyBy(
                fn ($voucher, int $index) => $lines[$index]['employee_item_id']
            );
            $adjustmentByItem = collect($adjustments)->keyBy(
                fn ($voucher, int $index) => $adjustmentLines[$index]['employee_item_id']
            );
            $updated = collect();

            foreach ($prepared as $itemId => $row) {
                /** @var PayrollItem $item */
                $item = $row['item'];
                $voucher = $postedByItem->get($itemId);
                $adjustmentVoucher = $adjustmentByItem->get($itemId);
                $salaryPayment = null;

                if ($voucher) {
                    $salaryPayment = EmployeeSalaryPayment::query()->create([
                        'employee_id' => $item->employee_id,
                        'payment_voucher_id' => $voucher->id,
                        'voucher_transaction_type_id' => $monthlySalaryType->id,
                        'salary_month' => $period->month,
                        'salary_year' => $period->year,
                        'amount' => $voucher->amount,
                        'status' => EmployeeSalaryPayment::STATUS_PAID,
                        'created_by' => auth()->id(),
                    ]);
                }

                $item->update([
                    'advance_balance' => $row['advance_balance'],
                    'advance_applied' => $row['advance_applied'],
                    'net_payable' => $row['net_payable'],
                    'advance_adjustment_voucher_id' => $adjustmentVoucher?->id,
                    'payment_voucher_id' => $voucher?->id,
                    'employee_salary_payment_id' => $salaryPayment?->id,
                    'status' => PayrollItem::STATUS_PAID,
                    'processed_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
                $updated->push($item->fresh(['employee', 'paymentVoucher']));
            }

            if (! $period->items()->pending()->exists()) {
                $period->update([
                    'status' => PayrollPeriod::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            return $updated;
        }, 3);
    }

    public function lock(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== PayrollPeriod::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'period' => 'Only completed payroll periods can be locked.',
                ]);
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_LOCKED,
                'locked_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            return $period;
        }, 3);
    }

    private function snapshot(PayrollPeriod $period, Employee $employee): void
    {
        $structure = $employee->salaryStructure;
        $paymentAccount = $employee->paymentAccount;
        $gross = round(
            (float) $structure->basic_salary
                + (float) $structure->home_rent_amount
                + (float) $structure->medical_amount
                + (float) $structure->conveyance_amount
                + (float) $structure->other_allowances,
            4
        );
        $net = round((float) $structure->gross_salary, 4);
        $payload = [
            'employee_id' => $employee->id,
            'employee_name' => $employee->employee_name,
            'employee_code' => $employee->employee_code,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'gross_salary' => $gross,
            'net_salary' => $net,
            'payment_account_id' => $employee->payment_account_id,
            'payment_method' => $paymentAccount
                ? $this->paymentAccounts->methodFor($paymentAccount)
                : null,
        ];

        $snapshot = PayrollSnapshot::query()->firstOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'department_id' => $employee->department_id,
                'designation_id' => $employee->designation_id,
                'employee_name' => $employee->employee_name,
                'employee_code' => $employee->employee_code,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'basic_salary' => $structure->basic_salary,
                'home_rent_percent' => $structure->home_rent_percent,
                'home_rent_amount' => $structure->home_rent_amount,
                'medical_percent' => $structure->medical_percent,
                'medical_amount' => $structure->medical_amount,
                'conveyance_percent' => $structure->conveyance_percent,
                'conveyance_amount' => $structure->conveyance_amount,
                'other_allowances' => $structure->other_allowances,
                'deductions' => $structure->deductions,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'payment_account_id' => $employee->payment_account_id,
                'payment_method' => $payload['payment_method'],
                'snapshot_hash' => hash('sha256', json_encode([
                    ...$payload,
                    'basic_salary' => (float) $structure->basic_salary,
                    'net_salary' => $net,
                ], JSON_THROW_ON_ERROR)),
            ]
        );

        PayrollItem::query()->firstOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'payroll_snapshot_id' => $snapshot->id,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'net_payable' => $net,
                'status' => PayrollItem::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]
        );
    }

    private function employeeMonthlySalaryType(): VoucherTransactionType
    {
        $type = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,status')
            ->whereHas('voucherCategory', fn ($query) => $query
                ->where('code', VoucherCategoryHelper::employeeCode())
                ->where('status', true))
            ->where('code', VoucherTransactionTypeHelper::monthlySalaryCode())
            ->where('voucher_type', VoucherTransactionTypeHelper::paymentVoucherType())
            ->where('status', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'period' => 'Monthly Salary transaction type is not configured.',
            ]);
        }

        return $type;
    }

    private function employeeAdvanceReturnType(): VoucherTransactionType
    {
        $type = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,status')
            ->whereHas('voucherCategory', fn ($query) => $query
                ->where('code', VoucherCategoryHelper::employeeCode())
                ->where('status', true))
            ->where('code', VoucherTransactionTypeHelper::employeeAdvanceReturnCode())
            ->where('voucher_type', VoucherTransactionTypeHelper::receiptVoucherType())
            ->where('status', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'period' => 'Employee Advance Return transaction type is not configured.',
            ]);
        }

        return $type;
    }
}
