<?php

namespace App\Http\Resources;

use App\Models\EmployeeSalaryPayment;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paymentAccount = $this->paymentAccount;
        $paymentMethod = $paymentAccount
            ? app(PaymentAccountService::class)->methodFor($paymentAccount)
            : null;
        $salaryPayment = $this->salaryPayments->first();
        $isPaid = $salaryPayment?->status
                === EmployeeSalaryPayment::STATUS_PAID
            && $salaryPayment->paymentVoucher?->journalEntry?->status
                === 'posted';
        $grossSalary = (float) ($this->salaryStructure?->gross_salary ?? 0);
        $configurationError = $this->configurationError(
            $paymentMethod,
            $grossSalary
        );

        return [
            'id' => $this->id,
            'employee_name' => $this->employee_name,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'designation' => $this->designation ? [
                'id' => $this->designation->id,
                'name' => $this->designation->name,
            ] : null,
            'payment_method' => $paymentMethod,
            'payment_account' => $paymentAccount ? [
                'id' => $paymentAccount->id,
                'name' => $paymentAccount->name,
                'ac_number' => $paymentAccount->ac_number,
            ] : null,
            'monthly_salary' => $grossSalary,
            'pay_amount' => $grossSalary,
            'payment_status' => $isPaid ? 'already_paid' : 'pending',
            'can_select' => ! $isPaid && $configurationError === null,
            'configuration_error' => $configurationError,
            'payment_voucher' => $isPaid ? [
                'id' => $salaryPayment->paymentVoucher->id,
                'voucher_no' => $salaryPayment->paymentVoucher->voucher_no,
                'date' => $salaryPayment->paymentVoucher->voucher_date?->format('Y-m-d'),
            ] : null,
        ];
    }

    private function configurationError(
        ?string $paymentMethod,
        float $grossSalary
    ): ?string {
        if (! $this->account || ! $this->account->status) {
            return 'Employee ledger account is unavailable.';
        }

        if ($grossSalary <= 0) {
            return 'Gross salary is not configured.';
        }

        if (! $this->paymentAccount) {
            return 'Payment account is not configured.';
        }

        if ($paymentMethod === null) {
            return 'Payment account is not valid for salary payment.';
        }

        return null;
    }
}
