<?php

namespace App\Services;

use App\Helpers\SalaryPaymentHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryPaymentService
{
    public function __construct(
        private readonly VoucherPostingService $vouchers,
        private readonly PaymentAccountService $paymentAccounts
    ) {}

    /**
     * @return Collection<int, EmployeeSalaryPayment>
     */
    public function pay(array $data): Collection
    {
        try {
            return DB::transaction(
                fn (): Collection => $this->postBatch($data),
                3
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $duplicate = EmployeeSalaryPayment::query()
                ->with('employee:id,employee_name')
                ->forPeriod(
                    (int) $data['salary_month'],
                    (int) $data['salary_year']
                )
                ->where(
                    'voucher_transaction_type_id',
                    (int) $data['voucher_transaction_type_id']
                )
                ->where('status', EmployeeSalaryPayment::STATUS_PAID)
                ->whereIn('employee_id', $data['employee_ids'])
                ->first();
            $transactionType = VoucherTransactionType::query()
                ->find((int) $data['voucher_transaction_type_id']);

            if ($duplicate?->employee && $transactionType) {
                throw $this->duplicatePaymentException(
                    $duplicate->employee,
                    $transactionType,
                    (int) $data['salary_month'],
                    (int) $data['salary_year']
                );
            }

            throw $exception;
        }
    }

    private function postBatch(array $data): Collection
    {
        $employeeIds = collect($data['employee_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->where('status', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->with([
                'account:id,name,ac_number,status',
                'paymentAccount.group:id,code,name,account_class,status',
                'salaryStructure',
            ])
            ->get()
            ->keyBy('id');

        if ($employees->count() !== $employeeIds->count()) {
            throw ValidationException::withMessages([
                'employee_ids' => 'One or more selected employees are unavailable.',
            ]);
        }

        $month = (int) $data['salary_month'];
        $year = (int) $data['salary_year'];
        [$category, $transactionType] = $this->salaryVoucherMasters(
            (int) $data['voucher_transaction_type_id']
        );
        $isMonthlySalary = $transactionType->code
            === VoucherTransactionTypeHelper::monthlySalaryCode();
        $existingPayments = EmployeeSalaryPayment::query()
            ->whereIn('employee_id', $employeeIds)
            ->forPeriod($month, $year)
            ->where('voucher_transaction_type_id', $transactionType->id)
            ->with('paymentVoucher.journalEntry:id,status')
            ->lockForUpdate()
            ->get()
            ->keyBy('employee_id');

        foreach ($employees as $employee) {
            $existing = $existingPayments->get($employee->id);

            if ($this->isEffectivePayment($existing)) {
                throw $this->duplicatePaymentException(
                    $employee,
                    $transactionType,
                    $month,
                    $year
                );
            }
        }

        $voucherLines = $employees
            ->map(function (Employee $employee) use (
                $category,
                $transactionType,
                $isMonthlySalary,
                $data,
                $month,
                $year
            ): array {
                $amount = $isMonthlySalary
                    ? (float) ($employee->salaryStructure?->gross_salary ?? 0)
                    : (float) ($data['amounts'][$employee->id] ?? 0);
                $paymentAccount = $employee->paymentAccount;
                $paymentMethod = $paymentAccount
                    ? $this->paymentAccounts->methodFor($paymentAccount)
                    : null;

                if (! $employee->account || ! $employee->account->status) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "{$employee->employee_name}'s ledger account is unavailable.",
                    ]);
                }

                if ($isMonthlySalary && $amount <= 0) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "Gross salary is not configured for {$employee->employee_name}.",
                    ]);
                }

                if (! $isMonthlySalary && $amount <= 0) {
                    throw ValidationException::withMessages([
                        "amounts.{$employee->id}" => "Enter a valid {$transactionType->name} amount for {$employee->employee_name}.",
                    ]);
                }

                if (! $paymentAccount || $paymentMethod === null) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "A valid payment account is not configured for {$employee->employee_name}.",
                    ]);
                }

                $remarks = trim((string) (
                    $data['remarks'][$employee->id]
                    ?? SalaryPaymentHelper::transactionRemarks(
                        $transactionType->name,
                        $employee->employee_name,
                        $month,
                        $year,
                        $isMonthlySalary
                    )
                ));
                $remarks = $remarks !== ''
                    ? $remarks
                    : SalaryPaymentHelper::transactionRemarks(
                        $transactionType->name,
                        $employee->employee_name,
                        $month,
                        $year,
                        $isMonthlySalary
                    );

                return [
                    'voucher_category_id' => $category->id,
                    'voucher_transaction_type_id' => $transactionType->id,
                    'from_account_id' => $paymentAccount->id,
                    'to_account_id' => $employee->account->id,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'description' => $remarks,
                    'remarks' => $remarks,
                ];
            })
            ->values()
            ->all();

        $postedVouchers = $this->vouchers->createMany(
            VoucherTransactionTypeHelper::paymentVoucherType(),
            [
                'date' => $data['date'],
                'vouchers' => $voucherLines,
            ]
        );

        return $employees
            ->values()
            ->map(function (
                Employee $employee,
                int $index
            ) use (
                $existingPayments,
                $month,
                $year,
                $transactionType,
                $postedVouchers
            ): EmployeeSalaryPayment {
                $payment = $existingPayments->get($employee->id)
                    ?? new EmployeeSalaryPayment([
                        'employee_id' => $employee->id,
                        'salary_month' => $month,
                        'salary_year' => $year,
                        'voucher_transaction_type_id' => $transactionType->id,
                    ]);

                $payment->fill([
                    'payment_voucher_id' => $postedVouchers[$index]->id,
                    'voucher_transaction_type_id' => $transactionType->id,
                    'amount' => $postedVouchers[$index]->amount,
                    'status' => EmployeeSalaryPayment::STATUS_PAID,
                    'created_by' => auth()->id(),
                ]);
                $payment->save();

                return $payment->refresh();
            });
    }

    /**
     * @return array{0: VoucherCategory, 1: VoucherTransactionType}
     */
    private function salaryVoucherMasters(
        int $transactionTypeId
    ): array {
        $transactionType = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,status')
            ->whereKey($transactionTypeId)
            ->where(
                'voucher_type',
                VoucherTransactionTypeHelper::paymentVoucherType()
            )
            ->where('status', true)
            ->first();

        $category = $transactionType?->voucherCategory;

        if (
            ! $transactionType
            || ! $category
            || ! $category->status
            || $category->code !== VoucherCategoryHelper::employeeCode()
        ) {
            throw ValidationException::withMessages([
                'voucher_transaction_type_id' => 'The selected employee payment type is not available.',
            ]);
        }

        return [$category, $transactionType];
    }

    private function isEffectivePayment(
        ?EmployeeSalaryPayment $payment
    ): bool {
        return $payment?->status === EmployeeSalaryPayment::STATUS_PAID
            && $payment->paymentVoucher?->journalEntry?->status === 'posted';
    }

    private function duplicatePaymentException(
        Employee $employee,
        VoucherTransactionType $transactionType,
        int $month,
        int $year
    ): ValidationException {
        $message = $transactionType->code
            === VoucherTransactionTypeHelper::monthlySalaryCode()
            ? 'Salary has already been paid for this employee for %s.'
            : "{$transactionType->name} has already been paid for this employee for %s.";

        return ValidationException::withMessages([
            'employee_ids' => sprintf(
                $message,
                SalaryPaymentHelper::periodLabel($month, $year)
            ),
        ]);
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
