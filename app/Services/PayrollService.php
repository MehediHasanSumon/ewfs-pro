<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\PayrollExtra;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollSnapshot;
use App\Models\PayrollVoucherLink;
use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers
    ) {}

    public function createPeriod(array $data): PayrollPeriod
    {
        try {
            return DB::transaction(function () use ($data): PayrollPeriod {
                return PayrollPeriod::query()->create([
                    'payroll_code' => $this->numbers->nextGlobal(
                        'payroll',
                        'PR',
                        6
                    ),
                    'month' => (int) $data['month'],
                    'year' => (int) $data['year'],
                    'remarks' => $data['remarks'] ?? null,
                    'payable_date' => $data['payable_date'] ?? null,
                    'status' => PayrollPeriod::STATUS_DRAFT,
                    'created_by' => auth()->id(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw ValidationException::withMessages([
                    'month' => 'A payroll already exists for this month and year.',
                ]);
            }

            throw $exception;
        }
    }

    public function updatePeriod(
        PayrollPeriod $period,
        array $data
    ): PayrollPeriod {
        return DB::transaction(function () use ($period, $data): PayrollPeriod {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->id);

            if (! in_array($period->status, [
                PayrollPeriod::STATUS_DRAFT,
                PayrollPeriod::STATUS_GENERATED,
            ], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Only draft or unpaid generated payrolls can be edited.',
                ]);
            }

            if ($period->hasPostedPayments()) {
                throw ValidationException::withMessages([
                    'payroll' => 'A payroll with posted vouchers cannot be edited.',
                ]);
            }

            if (
                $period->status !== PayrollPeriod::STATUS_DRAFT
                && (
                    (int) $data['month'] !== $period->month
                    || (int) $data['year'] !== $period->year
                )
            ) {
                throw ValidationException::withMessages([
                    'month' => 'Month and year cannot change after payroll generation.',
                ]);
            }

            $period->update([
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
                'remarks' => $data['remarks'] ?? null,
                'payable_date' => $data['payable_date'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            return $period->fresh();
        }, 3);
    }

    public function generate(
        PayrollPeriod $period,
        array $data
    ): PayrollPeriod {
        return DB::transaction(function () use ($period, $data): PayrollPeriod {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->id);

            if (! in_array($period->status, [
                PayrollPeriod::STATUS_DRAFT,
                PayrollPeriod::STATUS_GENERATED,
            ], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Only draft or unpaid generated payrolls can be generated.',
                ]);
            }

            if ($period->hasVoucherHistory()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll cannot be regenerated after voucher creation. Reverse or cancel the accounting workflow first.',
                ]);
            }

            $employeeRows = collect($data['employees'] ?? [])
                ->mapWithKeys(fn (array $row): array => [
                    (int) $row['employee_id'] => $row,
                ]);

            if ($employeeRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'employees' => 'Select at least one employee.',
                ]);
            }

            if ($period->status === PayrollPeriod::STATUS_GENERATED) {
                return $this->regenerateGeneratedPayroll(
                    $period,
                    $employeeRows
                );
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_PROCESSING,
                'started_at' => now(),
                'generated_at' => null,
                'completed_at' => null,
                'updated_by' => auth()->id(),
            ]);

            $employees = Employee::query()
                ->whereIn('id', $employeeRows->keys())
                ->where('status', true)
                ->with([
                    'account:id,name,ac_number,status',
                    'salaryStructure',
                    'department:id,name',
                    'designation:id,name',
                    'paymentAccount.group:id,code,name,account_class,status',
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($employees->count() !== $employeeRows->count()) {
                throw ValidationException::withMessages([
                    'employees' => 'One or more selected employees are unavailable.',
                ]);
            }

            $this->assertEmployeesConfigured($employees);
            $extraTypes = $this->validatedExtraTypes($employeeRows);
            $metrics = $this->ledger->employeeFinancialMetrics($employees);
            $this->clearGeneratedData($period);
            $deductionRows = [];
            $extraRows = [];

            foreach ($employees as $employee) {
                $input = $employeeRows->get($employee->id, []);
                $deductions = collect($input['deductions'] ?? [])
                    ->map(fn (array $deduction): array => [
                        'amount' => round((float) $deduction['amount'], 4),
                        'reason' => trim((string) $deduction['reason']),
                    ]);
                $extras = collect($input['extras'] ?? [])
                    ->map(fn (array $extra): array => [
                        'voucher_transaction_type_id' => (int) $extra['voucher_transaction_type_id'],
                        'amount' => round((float) $extra['amount'], 4),
                        'remarks' => trim((string) ($extra['remarks'] ?? '')),
                    ]);
                $monthlySalary = round(
                    (float) $employee->salaryStructure->gross_salary,
                    4
                );
                $totalDeduction = round(
                    (float) $deductions->sum('amount'),
                    4
                );

                if ($totalDeduction >= $monthlySalary) {
                    throw ValidationException::withMessages([
                        'employees' => "Salary deductions for {$employee->employee_name} must be less than the monthly salary.",
                    ]);
                }

                $salaryAfterDeduction = round(
                    $monthlySalary - $totalDeduction,
                    4
                );
                $advanceBalance = max(
                    0.0,
                    (float) (
                        $metrics->get($employee->id)['net_advance']
                        ?? 0
                    )
                );
                $advanceApplied = round(
                    min($salaryAfterDeduction, $advanceBalance),
                    4
                );
                $salaryPayable = round(
                    $salaryAfterDeduction - $advanceApplied,
                    4
                );
                $totalBonus = round((float) $extras->sum('amount'), 4);
                $netPayable = round($salaryPayable + $totalBonus, 4);
                $snapshot = $this->createSnapshot(
                    $period,
                    $employee,
                    $monthlySalary
                );
                $grossSalary = $this->salaryBeforeStructureDeductions(
                    $employee
                );
                $item = PayrollItem::query()->create([
                    'payroll_period_id' => $period->id,
                    'payroll_snapshot_id' => $snapshot->id,
                    'employee_id' => $employee->id,
                    'monthly_salary' => $monthlySalary,
                    'gross_salary' => $grossSalary,
                    'net_salary' => $netPayable,
                    'total_deduction' => $totalDeduction,
                    'total_bonus' => $totalBonus,
                    'advance_balance' => $advanceBalance,
                    'advance_applied' => $advanceApplied,
                    'salary_payable' => $salaryPayable,
                    'loan_balance' => max(
                        0.0,
                        (float) (
                            $metrics->get($employee->id)['loan_balance']
                            ?? 0
                        )
                    ),
                    'net_payable' => $netPayable,
                    'status' => PayrollItem::STATUS_PENDING,
                    'created_by' => auth()->id(),
                ]);

                foreach ($deductions as $deduction) {
                    $deductionRows[] = [
                        'payroll_item_id' => $item->id,
                        'amount' => $deduction['amount'],
                        'reason' => $deduction['reason'],
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach ($extras as $extra) {
                    if (! $extraTypes->has($extra['voucher_transaction_type_id'])) {
                        throw ValidationException::withMessages([
                            'employees' => 'One or more bonus or extra payment transaction types are unavailable.',
                        ]);
                    }

                    $extraRows[] = [
                        'payroll_item_id' => $item->id,
                        'voucher_transaction_type_id' => $extra['voucher_transaction_type_id'],
                        'amount' => $extra['amount'],
                        'remarks' => $extra['remarks'] !== ''
                            ? $extra['remarks']
                            : null,
                        'status' => PayrollExtra::STATUS_PENDING,
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($deductionRows !== []) {
                DB::table('payroll_deductions')->insert($deductionRows);
            }

            if ($extraRows !== []) {
                DB::table('payroll_extras')->insert($extraRows);
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_GENERATED,
                'generated_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            return $period->fresh([
                'snapshots',
                'items.deductions',
                'items.extras.voucherTransactionType',
            ]);
        }, 3);
    }

    public function start(PayrollPeriod $period): PayrollPeriod
    {
        $employees = Employee::query()
            ->where('status', true)
            ->whereHas('salaryStructure')
            ->orderBy('id')
            ->get(['id']);

        return $this->generate($period, [
            'employees' => $employees
                ->map(fn (Employee $employee): array => [
                    'employee_id' => $employee->id,
                    'deductions' => [],
                    'extras' => [],
                ])
                ->all(),
        ]);
    }

    public function pay(
        PayrollPeriod $period,
        array $data
    ): Collection {
        return DB::transaction(function () use ($period, $data): Collection {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->id);

            if ($period->status !== PayrollPeriod::STATUS_GENERATED) {
                throw ValidationException::withMessages([
                    'payroll' => 'Only generated payrolls can be paid.',
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
                    'paymentVoucher.journalEntry',
                    'advanceAdjustmentVoucher.journalEntry',
                    'extras.voucherTransactionType.voucherCategory',
                    'extras.paymentVoucher.journalEntry',
                ])
                ->orderBy('employee_id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $employeeIds->count()) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'One or more selected employees are unavailable or already paid.',
                ]);
            }

            $monthlySalaryType = $this->employeeMonthlySalaryType();
            $advanceReturnType = null;
            $salaryExpense = $this->systemAccounts->payrollSalaryExpense();
            $metrics = $this->ledger->employeeFinancialMetrics(
                $items->pluck('employee')->filter()->values()
            );
            $existingPayments = EmployeeSalaryPayment::query()
                ->whereIn('employee_id', $employeeIds)
                ->forPeriod($period->month, $period->year)
                ->where(
                    'voucher_transaction_type_id',
                    $monthlySalaryType->id
                )
                ->with('paymentVoucher.journalEntry')
                ->lockForUpdate()
                ->get()
                ->keyBy('employee_id');
            $adjustmentLines = [];
            $adjustmentTargets = [];
            $paymentLines = [];
            $paymentTargets = [];

            foreach ($items as $item) {
                $snapshot = $item->snapshot;
                $employee = $item->employee;
                $paymentAccount = $snapshot?->paymentAccount;
                $paymentMethod = $snapshot?->payment_method;

                if (! $snapshot || ! $employee) {
                    throw ValidationException::withMessages([
                        'employee_ids' => 'Payroll snapshot data is incomplete.',
                    ]);
                }

                if (
                    ((float) $item->salary_payable > 0
                        || $item->extras->isNotEmpty())
                    && (! $paymentAccount || ! $paymentMethod)
                ) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "{$snapshot->employee_name} has no valid payroll payment account.",
                    ]);
                }

                $advanceNeedsPayment = (float) $item->advance_applied > 0
                    && ! $this->voucherIsPosted(
                        $item->advanceAdjustmentVoucher
                    );
                $salaryNeedsPayment = (float) $item->salary_payable > 0
                    && ! $this->voucherIsPosted($item->paymentVoucher);
                $existingPayment = $existingPayments->get($item->employee_id);

                if ($salaryNeedsPayment && $this->salaryPaymentIsEffective(
                    $existingPayment
                )) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "Salary has already been paid for this payroll for {$snapshot->employee_name}.",
                    ]);
                }

                if ($advanceNeedsPayment) {
                    $currentAdvance = max(
                        0.0,
                        (float) (
                            $metrics->get($item->employee_id)['net_advance']
                            ?? 0
                        )
                    );

                    if (
                        round((float) $item->advance_applied, 4)
                            > round($currentAdvance, 4)
                    ) {
                        throw ValidationException::withMessages([
                            'employee_ids' => "{$snapshot->employee_name}'s salary advance balance changed after payroll generation. Regenerate the unpaid payroll before payment.",
                        ]);
                    }

                    $advanceReturnType ??= $this->employeeAdvanceReturnType();
                    $adjustmentTargets[] = $item;
                    $adjustmentLines[] = [
                        'voucher_category_id' => $advanceReturnType->voucher_category_id,
                        'voucher_transaction_type_id' => $advanceReturnType->id,
                        'from_account_id' => $employee->account_id,
                        'to_account_id' => $salaryExpense->id,
                        'amount' => (float) $item->advance_applied,
                        'payment_method' => 'journal',
                        'description' => "Salary advance adjustment for {$snapshot->employee_name} for {$period->label()}.",
                        'remarks' => "Salary advance adjustment for {$snapshot->employee_name} for {$period->label()}.",
                    ];
                }

                if ($salaryNeedsPayment) {
                    $paymentTargets[] = [
                        'role' => PayrollVoucherLink::ROLE_SALARY,
                        'item' => $item,
                        'extra' => null,
                        'existing_payment' => $existingPayment,
                    ];
                    $paymentLines[] = [
                        'voucher_category_id' => $monthlySalaryType->voucher_category_id,
                        'voucher_transaction_type_id' => $monthlySalaryType->id,
                        'from_account_id' => $snapshot->payment_account_id,
                        'to_account_id' => $salaryExpense->id,
                        'employee_id' => $employee->id,
                        'amount' => (float) $item->salary_payable,
                        'payment_method' => $paymentMethod,
                        'description' => "Monthly salary payment for {$snapshot->employee_name} for {$period->label()}.",
                        'remarks' => "Monthly salary payment for {$snapshot->employee_name} for {$period->label()}.",
                    ];
                }

                foreach ($item->extras as $extra) {
                    if (
                        $extra->status === PayrollExtra::STATUS_PAID
                        && $this->voucherIsPosted($extra->paymentVoucher)
                    ) {
                        continue;
                    }

                    $type = $extra->voucherTransactionType;

                    if (! $this->isValidExtraType($type)) {
                        throw ValidationException::withMessages([
                            'employee_ids' => "An extra payment type for {$snapshot->employee_name} is no longer available.",
                        ]);
                    }

                    $description = trim((string) $extra->remarks);
                    $description = $description !== ''
                        ? $description
                        : "{$type->name} for {$snapshot->employee_name} for {$period->label()}.";
                    $paymentTargets[] = [
                        'role' => PayrollVoucherLink::ROLE_EXTRA,
                        'item' => $item,
                        'extra' => $extra,
                        'existing_payment' => null,
                    ];
                    $paymentLines[] = [
                        'voucher_category_id' => $type->voucher_category_id,
                        'voucher_transaction_type_id' => $type->id,
                        'from_account_id' => $snapshot->payment_account_id,
                        'to_account_id' => $salaryExpense->id,
                        'employee_id' => $employee->id,
                        'amount' => (float) $extra->amount,
                        'payment_method' => $paymentMethod,
                        'description' => $description,
                        'remarks' => $description,
                    ];
                }
            }

            if ($adjustmentLines === [] && $paymentLines === []) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'The selected payroll employees are already fully paid.',
                ]);
            }

            $adjustmentVouchers = $adjustmentLines === []
                ? []
                : $this->vouchers->createMany(
                    VoucherTransactionTypeHelper::receiptVoucherType(),
                    [
                        'date' => $data['date'],
                        'vouchers' => $adjustmentLines,
                    ]
                );
            $paymentVouchers = $paymentLines === []
                ? []
                : $this->vouchers->createMany(
                    VoucherTransactionTypeHelper::paymentVoucherType(),
                    [
                        'date' => $data['date'],
                        'vouchers' => $paymentLines,
                    ]
                );

            foreach ($adjustmentVouchers as $index => $voucher) {
                /** @var PayrollItem $item */
                $item = $adjustmentTargets[$index];
                $item->update([
                    'advance_adjustment_voucher_id' => $voucher->id,
                    'updated_by' => auth()->id(),
                ]);
                PayrollVoucherLink::query()->create([
                    'payroll_item_id' => $item->id,
                    'voucher_id' => $voucher->id,
                    'role' => PayrollVoucherLink::ROLE_ADVANCE_ADJUSTMENT,
                    'status' => PayrollVoucherLink::STATUS_POSTED,
                ]);
            }

            foreach ($paymentVouchers as $index => $voucher) {
                $target = $paymentTargets[$index];
                /** @var PayrollItem $item */
                $item = $target['item'];
                /** @var PayrollExtra|null $extra */
                $extra = $target['extra'];

                if ($target['role'] === PayrollVoucherLink::ROLE_SALARY) {
                    $salaryPayment = $target['existing_payment']
                        ?? new EmployeeSalaryPayment([
                            'employee_id' => $item->employee_id,
                            'voucher_transaction_type_id' => $monthlySalaryType->id,
                            'salary_month' => $period->month,
                            'salary_year' => $period->year,
                        ]);
                    $salaryPayment->fill([
                        'payment_voucher_id' => $voucher->id,
                        'amount' => $voucher->amount,
                        'status' => EmployeeSalaryPayment::STATUS_PAID,
                    ]);

                    if (! $salaryPayment->exists) {
                        $salaryPayment->created_by = auth()->id();
                    }

                    $salaryPayment->save();
                    $item->update([
                        'payment_voucher_id' => $voucher->id,
                        'employee_salary_payment_id' => $salaryPayment->id,
                        'updated_by' => auth()->id(),
                    ]);
                    PayrollVoucherLink::query()->create([
                        'payroll_item_id' => $item->id,
                        'voucher_id' => $voucher->id,
                        'role' => PayrollVoucherLink::ROLE_SALARY,
                        'status' => PayrollVoucherLink::STATUS_POSTED,
                    ]);
                } else {
                    $extra->update([
                        'payment_voucher_id' => $voucher->id,
                        'status' => PayrollExtra::STATUS_PAID,
                    ]);
                    PayrollVoucherLink::query()->create([
                        'payroll_item_id' => $item->id,
                        'payroll_extra_id' => $extra->id,
                        'voucher_id' => $voucher->id,
                        'role' => PayrollVoucherLink::ROLE_EXTRA,
                        'status' => PayrollVoucherLink::STATUS_POSTED,
                    ]);
                }
            }

            $this->synchronizePeriod($period);

            return PayrollItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->with([
                    'employee',
                    'paymentVoucher',
                    'advanceAdjustmentVoucher',
                    'extras.paymentVoucher',
                ])
                ->get();
        }, 3);
    }

    public function process(
        PayrollPeriod $period,
        array $data
    ): Collection {
        return $this->pay($period, $data);
    }

    public function cancel(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period): PayrollPeriod {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->id);

            if (! in_array($period->status, [
                PayrollPeriod::STATUS_DRAFT,
                PayrollPeriod::STATUS_GENERATED,
            ], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Only draft or unpaid generated payrolls can be cancelled.',
                ]);
            }

            if ($period->hasVoucherHistory()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll with voucher history cannot be cancelled silently.',
                ]);
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            return $period;
        }, 3);
    }

    public function delete(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period): void {
            $period = PayrollPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->id);

            if (! in_array($period->status, [
                PayrollPeriod::STATUS_DRAFT,
                PayrollPeriod::STATUS_GENERATED,
                PayrollPeriod::STATUS_CANCELLED,
            ], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'This payroll cannot be deleted in its current status.',
                ]);
            }

            if ($period->hasVoucherHistory()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll with voucher history cannot be deleted.',
                ]);
            }

            $this->clearGeneratedData($period);
            $period->delete();
        }, 3);
    }

    public function synchronizePeriod(PayrollPeriod $period): PayrollPeriod
    {
        $period = PayrollPeriod::query()
            ->with([
                'items.paymentVoucher.journalEntry',
                'items.advanceAdjustmentVoucher.journalEntry',
                'items.extras.paymentVoucher.journalEntry',
            ])
            ->findOrFail($period->id);

        foreach ($period->items as $item) {
            $salaryPaid = (float) $item->salary_payable <= 0
                || $this->voucherIsPosted($item->paymentVoucher);
            $advanceAdjusted = (float) $item->advance_applied <= 0
                || $this->voucherIsPosted(
                    $item->advanceAdjustmentVoucher
                );
            $extrasPaid = $item->extras->every(
                fn (PayrollExtra $extra): bool => (
                    $extra->status === PayrollExtra::STATUS_PAID
                    && $this->voucherIsPosted($extra->paymentVoucher)
                )
            );
            $paid = $salaryPaid && $advanceAdjusted && $extrasPaid;
            $item->update([
                'status' => $paid
                    ? PayrollItem::STATUS_PAID
                    : PayrollItem::STATUS_PENDING,
                'processed_at' => $paid ? ($item->processed_at ?? now()) : null,
                'updated_by' => auth()->id(),
            ]);
        }

        $allPaid = $period->items->isNotEmpty()
            && $period->items->every(
                fn (PayrollItem $item): bool => (
                    $item->fresh()->status === PayrollItem::STATUS_PAID
                )
            );
        $period->update([
            'status' => $allPaid
                ? PayrollPeriod::STATUS_PAID
                : PayrollPeriod::STATUS_GENERATED,
            'completed_at' => $allPaid ? ($period->completed_at ?? now()) : null,
            'updated_by' => auth()->id(),
        ]);

        return $period->fresh();
    }

    private function createSnapshot(
        PayrollPeriod $period,
        Employee $employee,
        float $monthlySalary
    ): PayrollSnapshot {
        $structure = $employee->salaryStructure;
        $paymentMethod = $this->paymentAccounts->methodFor(
            $employee->paymentAccount
        );
        $grossSalary = $this->salaryBeforeStructureDeductions($employee);
        $payload = [
            'employee_id' => $employee->id,
            'employee_name' => $employee->employee_name,
            'employee_code' => $employee->employee_code,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'monthly_salary' => $monthlySalary,
            'payment_account_id' => $employee->payment_account_id,
            'payment_method' => $paymentMethod,
        ];

        return PayrollSnapshot::query()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'employee_name' => $employee->employee_name,
            'employee_code' => $employee->employee_code,
            'department_name' => $employee->department?->name,
            'designation_name' => $employee->designation?->name,
            'basic_salary' => $structure->basic_salary,
            'monthly_salary' => $monthlySalary,
            'home_rent_percent' => $structure->home_rent_percent,
            'home_rent_amount' => $structure->home_rent_amount,
            'medical_percent' => $structure->medical_percent,
            'medical_amount' => $structure->medical_amount,
            'conveyance_percent' => $structure->conveyance_percent,
            'conveyance_amount' => $structure->conveyance_amount,
            'other_allowances' => $structure->other_allowances,
            'deductions' => $structure->deductions,
            'gross_salary' => $grossSalary,
            'net_salary' => $monthlySalary,
            'payment_account_id' => $employee->payment_account_id,
            'payment_method' => $paymentMethod,
            'snapshot_hash' => hash(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR)
            ),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     */
    private function assertEmployeesConfigured(
        EloquentCollection $employees
    ): void {
        foreach ($employees as $employee) {
            if (! $employee->salaryStructure) {
                throw ValidationException::withMessages([
                    'employees' => "Active salary structure is not configured for {$employee->employee_name}.",
                ]);
            }

            if (! $employee->account || ! $employee->account->status) {
                throw ValidationException::withMessages([
                    'employees' => "{$employee->employee_name}'s employee ledger account is unavailable.",
                ]);
            }

            if (
                ! $employee->paymentAccount
                || $this->paymentAccounts->methodFor(
                    $employee->paymentAccount
                ) === null
            ) {
                throw ValidationException::withMessages([
                    'employees' => "A valid payment account is not configured for {$employee->employee_name}.",
                ]);
            }
        }
    }

    private function regenerateGeneratedPayroll(
        PayrollPeriod $period,
        Collection $employeeRows
    ): PayrollPeriod {
        $items = PayrollItem::query()
            ->where('payroll_period_id', $period->id)
            ->with([
                'snapshot.paymentAccount.group',
                'deductions',
                'extras',
            ])
            ->orderBy('employee_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('employee_id');
        $submittedIds = $employeeRows->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();
        $existingIds = $items->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        if ($submittedIds->all() !== $existingIds->all()) {
            throw ValidationException::withMessages([
                'employees' => 'Generated payroll employees cannot be added or removed. Create a new draft payroll if the employee set must change.',
            ]);
        }

        $employees = Employee::query()
            ->whereIn('id', $submittedIds)
            ->where('status', true)
            ->with([
                'account:id,name,ac_number,status',
                'salaryStructure',
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($employees->count() !== $submittedIds->count()) {
            throw ValidationException::withMessages([
                'employees' => 'One or more generated payroll employees are unavailable.',
            ]);
        }

        foreach ($items as $item) {
            $snapshot = $item->snapshot;
            $employee = $employees->get($item->employee_id);

            if (
                ! $snapshot
                || ! $employee
                || ! $employee->account
                || ! $employee->account->status
                || ! $snapshot->paymentAccount
                || $snapshot->payment_method === null
            ) {
                throw ValidationException::withMessages([
                    'employees' => 'One or more generated payroll snapshots are no longer payable.',
                ]);
            }
        }

        $period->update([
            'status' => PayrollPeriod::STATUS_PROCESSING,
            'started_at' => now(),
            'generated_at' => null,
            'completed_at' => null,
            'updated_by' => auth()->id(),
        ]);

        $extraTypes = $this->validatedExtraTypes($employeeRows);
        $metrics = $this->ledger->employeeFinancialMetrics(
            $employees->values()
        );
        $itemIds = $items->pluck('id');
        DB::table('payroll_deductions')
            ->whereIn('payroll_item_id', $itemIds)
            ->delete();
        DB::table('payroll_extras')
            ->whereIn('payroll_item_id', $itemIds)
            ->delete();
        $deductionRows = [];
        $extraRows = [];

        foreach ($items as $employeeId => $item) {
            $input = $employeeRows->get($employeeId, []);
            $deductions = collect($input['deductions'] ?? [])
                ->map(fn (array $deduction): array => [
                    'amount' => round((float) $deduction['amount'], 4),
                    'reason' => trim((string) $deduction['reason']),
                ]);
            $extras = collect($input['extras'] ?? [])
                ->map(fn (array $extra): array => [
                    'voucher_transaction_type_id' => (int) $extra['voucher_transaction_type_id'],
                    'amount' => round((float) $extra['amount'], 4),
                    'remarks' => trim((string) ($extra['remarks'] ?? '')),
                ]);
            $monthlySalary = round((float) (
                $item->snapshot->monthly_salary
                ?? $item->monthly_salary
            ), 4);
            $totalDeduction = round(
                (float) $deductions->sum('amount'),
                4
            );

            if ($monthlySalary <= 0 || $totalDeduction >= $monthlySalary) {
                throw ValidationException::withMessages([
                    'employees' => "Salary deductions for {$item->snapshot->employee_name} must be less than the snapshotted monthly salary.",
                ]);
            }

            $salaryAfterDeduction = round(
                $monthlySalary - $totalDeduction,
                4
            );
            $advanceBalance = max(
                0.0,
                (float) (
                    $metrics->get($employeeId)['net_advance']
                    ?? 0
                )
            );
            $advanceApplied = round(
                min($salaryAfterDeduction, $advanceBalance),
                4
            );
            $salaryPayable = round(
                $salaryAfterDeduction - $advanceApplied,
                4
            );
            $totalBonus = round((float) $extras->sum('amount'), 4);
            $netPayable = round($salaryPayable + $totalBonus, 4);

            $item->update([
                'monthly_salary' => $monthlySalary,
                'net_salary' => $netPayable,
                'total_deduction' => $totalDeduction,
                'total_bonus' => $totalBonus,
                'advance_balance' => $advanceBalance,
                'advance_applied' => $advanceApplied,
                'salary_payable' => $salaryPayable,
                'loan_balance' => max(
                    0.0,
                    (float) (
                        $metrics->get($employeeId)['loan_balance']
                        ?? 0
                    )
                ),
                'net_payable' => $netPayable,
                'status' => PayrollItem::STATUS_PENDING,
                'processed_at' => null,
                'updated_by' => auth()->id(),
            ]);

            foreach ($deductions as $deduction) {
                $deductionRows[] = [
                    'payroll_item_id' => $item->id,
                    'amount' => $deduction['amount'],
                    'reason' => $deduction['reason'],
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($extras as $extra) {
                if (! $extraTypes->has(
                    $extra['voucher_transaction_type_id']
                )) {
                    throw ValidationException::withMessages([
                        'employees' => 'One or more bonus or extra payment transaction types are unavailable.',
                    ]);
                }

                $extraRows[] = [
                    'payroll_item_id' => $item->id,
                    'voucher_transaction_type_id' => $extra['voucher_transaction_type_id'],
                    'amount' => $extra['amount'],
                    'remarks' => $extra['remarks'] !== ''
                        ? $extra['remarks']
                        : null,
                    'status' => PayrollExtra::STATUS_PENDING,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($deductionRows !== []) {
            DB::table('payroll_deductions')->insert($deductionRows);
        }

        if ($extraRows !== []) {
            DB::table('payroll_extras')->insert($extraRows);
        }

        $period->update([
            'status' => PayrollPeriod::STATUS_GENERATED,
            'generated_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $period->fresh([
            'snapshots',
            'items.deductions',
            'items.extras.voucherTransactionType',
        ]);
    }

    private function clearGeneratedData(PayrollPeriod $period): void
    {
        $itemIds = DB::table('payroll_items')
            ->where('payroll_period_id', $period->id)
            ->pluck('id');

        if ($itemIds->isNotEmpty()) {
            DB::table('payroll_deductions')
                ->whereIn('payroll_item_id', $itemIds)
                ->delete();
            DB::table('payroll_extras')
                ->whereIn('payroll_item_id', $itemIds)
                ->delete();
            DB::table('payroll_items')
                ->whereIn('id', $itemIds)
                ->delete();
        }

        DB::table('payroll_snapshots')
            ->where('payroll_period_id', $period->id)
            ->delete();
    }

    private function validatedExtraTypes(Collection $employeeRows): Collection
    {
        $typeIds = $employeeRows
            ->flatMap(fn (array $row): array => $row['extras'] ?? [])
            ->pluck('voucher_transaction_type_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($typeIds->isEmpty()) {
            return collect();
        }

        $types = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,status')
            ->whereIn('id', $typeIds)
            ->where('status', true)
            ->where(
                'voucher_type',
                VoucherTransactionTypeHelper::paymentVoucherType()
            )
            ->get()
            ->filter(fn (VoucherTransactionType $type): bool => (
                $this->isValidExtraType($type)
            ))
            ->keyBy('id');

        if ($types->count() !== $typeIds->count()) {
            throw ValidationException::withMessages([
                'employees' => 'One or more bonus or extra payment transaction types are invalid.',
            ]);
        }

        return $types;
    }

    private function isValidExtraType(
        ?VoucherTransactionType $type
    ): bool {
        return $type?->status
            && $type->voucher_type
                === VoucherTransactionTypeHelper::paymentVoucherType()
            && $type->voucherCategory?->status
            && $type->voucherCategory?->code
                === VoucherCategoryHelper::employeeCode()
            && $type->code
                !== VoucherTransactionTypeHelper::monthlySalaryCode();
    }

    private function employeeMonthlySalaryType(): VoucherTransactionType
    {
        return $this->employeeType(
            VoucherTransactionTypeHelper::monthlySalaryCode(),
            VoucherTransactionTypeHelper::paymentVoucherType(),
            'Monthly Salary'
        );
    }

    private function employeeAdvanceReturnType(): VoucherTransactionType
    {
        return $this->employeeType(
            VoucherTransactionTypeHelper::employeeAdvanceReturnCode(),
            VoucherTransactionTypeHelper::receiptVoucherType(),
            'Employee Advance Return'
        );
    }

    private function employeeType(
        string $code,
        string $voucherType,
        string $label
    ): VoucherTransactionType {
        $type = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,status')
            ->whereHas('voucherCategory', fn ($query) => $query
                ->where('code', VoucherCategoryHelper::employeeCode())
                ->where('status', true))
            ->where('code', $code)
            ->where('voucher_type', $voucherType)
            ->where('status', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'payroll' => "{$label} transaction type is not configured.",
            ]);
        }

        return $type;
    }

    private function salaryBeforeStructureDeductions(
        Employee $employee
    ): float {
        $structure = $employee->salaryStructure;

        return round(
            (float) $structure->basic_salary
                + (float) $structure->home_rent_amount
                + (float) $structure->medical_amount
                + (float) $structure->conveyance_amount
                + (float) $structure->other_allowances,
            4
        );
    }

    private function salaryPaymentIsEffective(
        ?EmployeeSalaryPayment $payment
    ): bool {
        return $payment?->status === EmployeeSalaryPayment::STATUS_PAID
            && $this->voucherIsPosted($payment->paymentVoucher);
    }

    private function voucherIsPosted(?Voucher $voucher): bool
    {
        return $voucher?->status === 'posted'
            && $voucher->journalEntry?->status === 'posted';
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return in_array(
            (string) $exception->getCode(),
            ['23000', '23505'],
            true
        );
    }
}
