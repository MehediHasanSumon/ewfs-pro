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
            'gross_salary' => (float) $this->gross_salary,
            'net_salary' => (float) $this->net_salary,
            'advance_balance' => (float) $this->advance_balance,
            'advance_applied' => (float) $this->advance_applied,
            'loan_balance' => (float) $this->loan_balance,
            'net_payable' => (float) $this->net_payable,
            'status' => $this->status,
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
