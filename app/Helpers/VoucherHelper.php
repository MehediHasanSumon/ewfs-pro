<?php

namespace App\Helpers;

use App\Models\Employee;
use App\Models\Voucher;
use App\Services\DocumentNumberService;

class VoucherHelper
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {}

    public function generateEmployeeCode(): string
    {
        $highestExistingNumber = Employee::query()
            ->whereNotNull('employee_code')
            ->pluck('employee_code')
            ->reduce(function (int $highest, string $code): int {
                if (! preg_match('/^EMP(\d+)$/', $code, $matches)) {
                    return $highest;
                }

                return max($highest, (int) $matches[1]);
            }, 0);

        return $this->numbers->nextGlobal(
            'employee',
            'EMP',
            6,
            $highestExistingNumber + 1
        );
    }

    public static function generateVoucherNo(): string
    {
        $lastVoucher = Voucher::orderBy('id', 'desc')->first();

        if (! $lastVoucher) {
            return 'V0001';
        }

        $lastNumber = (int) substr($lastVoucher->voucher_no, 1);
        $nextNumber = $lastNumber + 1;

        return 'V'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
