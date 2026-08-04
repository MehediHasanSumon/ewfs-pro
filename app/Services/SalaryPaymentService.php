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
                ->where('status', EmployeeSalaryPayment::STATUS_PAID)
                ->whereIn('employee_id', $data['employee_ids'])
                ->first();

            if ($duplicate?->employee) {
                throw $this->duplicatePaymentException(
                    $duplicate->employee,
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
        $existingPayments = EmployeeSalaryPayment::query()
            ->whereIn('employee_id', $employeeIds)
            ->forPeriod($month, $year)
            ->with('paymentVoucher.journalEntry:id,status')
            ->lockForUpdate()
            ->get()
            ->keyBy('employee_id');

        foreach ($employees as $employee) {
            $existing = $existingPayments->get($employee->id);

            if ($this->isEffectivePayment($existing)) {
                throw $this->duplicatePaymentException(
                    $employee,
                    $month,
                    $year
                );
            }
        }

        [$category, $transactionType] = $this->salaryVoucherMasters();
        $voucherLines = $employees
            ->map(function (Employee $employee) use (
                $category,
                $transactionType,
                $data,
                $month,
                $year
            ): array {
                $salary = (float) ($employee->salaryStructure?->gross_salary ?? 0);
                $paymentAccount = $employee->paymentAccount;
                $paymentMethod = $paymentAccount
                    ? $this->paymentAccounts->methodFor($paymentAccount)
                    : null;

                if (! $employee->account || ! $employee->account->status) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "{$employee->employee_name}'s ledger account is unavailable.",
                    ]);
                }

                if ($salary <= 0) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "Gross salary is not configured for {$employee->employee_name}.",
                    ]);
                }

                if (! $paymentAccount || $paymentMethod === null) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "A valid payment account is not configured for {$employee->employee_name}.",
                    ]);
                }

                $remarks = trim((string) (
                    $data['remarks'][$employee->id]
                    ?? SalaryPaymentHelper::remarks(
                        $employee->employee_name,
                        $month,
                        $year
                    )
                ));
                $remarks = $remarks !== ''
                    ? $remarks
                    : SalaryPaymentHelper::remarks(
                        $employee->employee_name,
                        $month,
                        $year
                    );

                return [
                    'voucher_category_id' => $category->id,
                    'voucher_transaction_type_id' => $transactionType->id,
                    'from_account_id' => $paymentAccount->id,
                    'to_account_id' => $employee->account->id,
                    'amount' => $salary,
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
                'shift_id' => $data['shift_id'],
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
                $postedVouchers
            ): EmployeeSalaryPayment {
                $payment = $existingPayments->get($employee->id)
                    ?? new EmployeeSalaryPayment([
                        'employee_id' => $employee->id,
                        'salary_month' => $month,
                        'salary_year' => $year,
                    ]);

                $payment->fill([
                    'payment_voucher_id' => $postedVouchers[$index]->id,
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
    private function salaryVoucherMasters(): array
    {
        $category = VoucherCategory::query()
            ->where('code', VoucherCategoryHelper::employeeCode())
            ->where('status', true)
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'salary_payment' => 'The Employee voucher category is not configured.',
            ]);
        }

        $transactionType = VoucherTransactionType::query()
            ->where('voucher_category_id', $category->id)
            ->where(
                'code',
                VoucherTransactionTypeHelper::monthlySalaryCode()
            )
            ->where(
                'voucher_type',
                VoucherTransactionTypeHelper::paymentVoucherType()
            )
            ->where('status', true)
            ->first();

        if (! $transactionType) {
            throw ValidationException::withMessages([
                'salary_payment' => 'The Monthly Salary payment transaction type is not configured.',
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
        int $month,
        int $year
    ): ValidationException {
        return ValidationException::withMessages([
            'employee_ids' => sprintf(
                'Salary has already been paid for this employee for %s.',
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
