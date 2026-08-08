<?php

namespace App\Helpers;

use Carbon\Carbon;

class SalaryPaymentHelper
{
    public static function viewPermission(): string
    {
        return 'salary-payment-view';
    }

    public static function createPermission(): string
    {
        return 'salary-payment-create';
    }

    public static function historyPermission(): string
    {
        return 'salary-payment-history';
    }

    public static function payrollViewPermission(): string
    {
        return 'payroll-view';
    }

    public static function payrollCreatePermission(): string
    {
        return 'payroll-create';
    }

    public static function payrollProcessPermission(): string
    {
        return 'payroll-process';
    }

    public static function payrollHistoryPermission(): string
    {
        return 'payroll-history';
    }

    public static function permissionNames(): array
    {
        return [
            self::viewPermission(),
            self::createPermission(),
            self::historyPermission(),
            self::payrollViewPermission(),
            self::payrollCreatePermission(),
            self::payrollProcessPermission(),
            self::payrollHistoryPermission(),
        ];
    }

    public static function periodLabel(int $month, int $year): string
    {
        return Carbon::create($year, $month, 1)->format('F Y');
    }

    public static function remarks(
        string $employeeName,
        int $month,
        int $year
    ): string {
        return sprintf(
            'Monthly salary payment for %s for %s.',
            $employeeName,
            self::periodLabel($month, $year)
        );
    }

    public static function transactionRemarks(
        string $transactionTypeName,
        string $employeeName,
        int $month,
        int $year,
        bool $isMonthlySalary = false
    ): string {
        if ($isMonthlySalary) {
            return self::remarks($employeeName, $month, $year);
        }

        return sprintf(
            '%s payment for %s for %s.',
            $transactionTypeName,
            $employeeName,
            self::periodLabel($month, $year)
        );
    }
}
