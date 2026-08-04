<?php

namespace App\Helpers;

use Carbon\Carbon;

class SalaryPaymentHelper
{
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
}
