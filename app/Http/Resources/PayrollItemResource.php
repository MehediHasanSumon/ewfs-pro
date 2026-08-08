<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->snapshot;
        $employee = $this->employee;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $snapshot?->employee_name ?? $employee?->employee_name,
            'employee_code' => $snapshot?->employee_code ?? $employee?->employee_code,
            'department' => $snapshot?->department_name ?? $employee?->department?->name,
            'designation' => $snapshot?->designation_name ?? $employee?->designation?->name,
            'payment_method' => $snapshot?->payment_method,
            'payment_account' => $snapshot?->paymentAccount ? [
                'id' => $snapshot->paymentAccount->id,
                'name' => $snapshot->paymentAccount->name,
                'ac_number' => $snapshot->paymentAccount->ac_number,
            ] : null,
            'monthly_salary' => (float) (
                $this->monthly_salary
                ?? $snapshot?->monthly_salary
                ?? $this->net_salary
            ),
            'gross_salary' => (float) $this->gross_salary,
            'net_salary' => (float) $this->net_salary,
            'total_deduction' => (float) $this->total_deduction,
            'total_bonus' => (float) $this->total_bonus,
            'advance_balance' => (float) $this->advance_balance,
            'advance_applied' => (float) $this->advance_applied,
            'salary_payable' => (float) $this->salary_payable,
            'loan_balance' => (float) $this->loan_balance,
            'net_payable' => (float) $this->net_payable,
            'status' => $this->status,
            'deductions' => $this->whenLoaded(
                'deductions',
                fn () => $this->deductions->map(fn ($deduction) => [
                    'id' => $deduction->id,
                    'amount' => (float) $deduction->amount,
                    'reason' => $deduction->reason,
                ])->values()
            ),
            'extras' => $this->whenLoaded(
                'extras',
                fn () => $this->extras->map(fn ($extra) => [
                    'id' => $extra->id,
                    'amount' => (float) $extra->amount,
                    'remarks' => $extra->remarks,
                    'status' => $extra->status,
                    'voucher_transaction_type' => $extra->voucherTransactionType ? [
                        'id' => $extra->voucherTransactionType->id,
                        'code' => $extra->voucherTransactionType->code,
                        'name' => $extra->voucherTransactionType->name,
                    ] : null,
                    'payment_voucher' => $extra->paymentVoucher ? [
                        'id' => $extra->paymentVoucher->id,
                        'voucher_no' => $extra->paymentVoucher->voucher_no,
                    ] : null,
                ])->values()
            ),
            'payment_voucher' => $this->paymentVoucher ? [
                'id' => $this->paymentVoucher->id,
                'voucher_no' => $this->paymentVoucher->voucher_no,
            ] : null,
            'advance_adjustment_voucher' => $this->advanceAdjustmentVoucher ? [
                'id' => $this->advanceAdjustmentVoucher->id,
                'voucher_no' => $this->advanceAdjustmentVoucher->voucher_no,
            ] : null,
        ];
    }
}
