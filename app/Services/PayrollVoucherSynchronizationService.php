<?php

namespace App\Services;

use App\Models\EmployeeSalaryPayment;
use App\Models\PayrollExtra;
use App\Models\PayrollItem;
use App\Models\PayrollVoucherLink;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class PayrollVoucherSynchronizationService
{
    public function __construct(
        private readonly VoucherPostingService $vouchers,
        private readonly PayrollService $payroll
    ) {}

    public function isPayrollVoucher(Voucher $voucher): bool
    {
        return PayrollVoucherLink::query()
            ->where('voucher_id', $voucher->id)
            ->exists()
            || PayrollItem::query()
                ->where(function ($query) use ($voucher): void {
                    $query
                        ->where('payment_voucher_id', $voucher->id)
                        ->orWhere(
                            'advance_adjustment_voucher_id',
                            $voucher->id
                        );
                })
                ->exists()
            || PayrollExtra::query()
                ->where('payment_voucher_id', $voucher->id)
                ->exists();
    }

    public function reverse(
        Voucher $voucher,
        string $reason = 'Payroll voucher reversed from the voucher workflow.'
    ): void {
        DB::transaction(function () use ($voucher, $reason): void {
            $voucher = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->id);
            $links = PayrollVoucherLink::query()
                ->where('voucher_id', $voucher->id)
                ->with(['payrollItem.period', 'payrollExtra'])
                ->lockForUpdate()
                ->get();

            if ($links->isEmpty()) {
                $item = PayrollItem::query()
                    ->where(function ($query) use ($voucher): void {
                        $query
                            ->where('payment_voucher_id', $voucher->id)
                            ->orWhere(
                                'advance_adjustment_voucher_id',
                                $voucher->id
                            );
                    })
                    ->lockForUpdate()
                    ->first();
                $extra = PayrollExtra::query()
                    ->where('payment_voucher_id', $voucher->id)
                    ->lockForUpdate()
                    ->first();

                if (! $item && ! $extra) {
                    $this->vouchers->reverse($voucher, $reason);

                    return;
                }

                $item ??= $extra?->payrollItem;
                $role = $extra
                    ? PayrollVoucherLink::ROLE_EXTRA
                    : (
                        $item?->advance_adjustment_voucher_id === $voucher->id
                            ? PayrollVoucherLink::ROLE_ADVANCE_ADJUSTMENT
                            : PayrollVoucherLink::ROLE_SALARY
                    );
                $link = PayrollVoucherLink::query()->create([
                    'payroll_item_id' => $item?->id,
                    'payroll_extra_id' => $extra?->id,
                    'voucher_id' => $voucher->id,
                    'role' => $role,
                    'status' => PayrollVoucherLink::STATUS_POSTED,
                ]);
                $links = collect([
                    $link->load(['payrollItem.period', 'payrollExtra']),
                ]);
            }

            $this->vouchers->reverse($voucher, $reason, true);
            $periods = collect();

            foreach ($links as $link) {
                $link->update([
                    'status' => PayrollVoucherLink::STATUS_REVERSED,
                ]);
                $item = $link->payrollItem;

                if (! $item) {
                    continue;
                }

                $periods->put($item->payroll_period_id, $item->period);

                if ($link->role === PayrollVoucherLink::ROLE_SALARY) {
                    EmployeeSalaryPayment::query()
                        ->where('payment_voucher_id', $voucher->id)
                        ->update([
                            'status' => EmployeeSalaryPayment::STATUS_REVERSED,
                            'updated_at' => now(),
                        ]);
                    $item->update([
                        'payment_voucher_id' => null,
                        'status' => PayrollItem::STATUS_PENDING,
                        'processed_at' => null,
                        'updated_by' => auth()->id(),
                    ]);
                }

                if (
                    $link->role
                        === PayrollVoucherLink::ROLE_ADVANCE_ADJUSTMENT
                ) {
                    $item->update([
                        'advance_adjustment_voucher_id' => null,
                        'status' => PayrollItem::STATUS_PENDING,
                        'processed_at' => null,
                        'updated_by' => auth()->id(),
                    ]);
                }

                if (
                    $link->role === PayrollVoucherLink::ROLE_EXTRA
                    && $link->payrollExtra
                ) {
                    $link->payrollExtra->update([
                        'payment_voucher_id' => null,
                        'status' => PayrollExtra::STATUS_REVERSED,
                    ]);
                    $item->update([
                        'status' => PayrollItem::STATUS_PENDING,
                        'processed_at' => null,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            foreach ($periods as $period) {
                $this->payroll->synchronizePeriod($period);
            }
        }, 3);
    }
}
