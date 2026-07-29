<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SalaryStructureService
{
    public function calculate(array $structure): array
    {
        $basicSalary = round((float) $structure['basic_salary'], 4);
        $homeRentPercent = round((float) ($structure['home_rent_percent'] ?? 0), 4);
        $medicalPercent = round((float) ($structure['medical_percent'] ?? 0), 4);
        $conveyancePercent = round((float) ($structure['conveyance_percent'] ?? 0), 4);
        $otherAllowances = round((float) ($structure['other_allowances'] ?? 0), 4);
        $deductions = round((float) ($structure['deductions'] ?? 0), 4);

        $homeRentAmount = $this->percentageAmount($basicSalary, $homeRentPercent);
        $medicalAmount = $this->percentageAmount($basicSalary, $medicalPercent);
        $conveyanceAmount = $this->percentageAmount($basicSalary, $conveyancePercent);
        $grossSalary = round(
            $basicSalary
                + $homeRentAmount
                + $medicalAmount
                + $conveyanceAmount
                + $otherAllowances
                - $deductions,
            4
        );

        if ($grossSalary <= 0) {
            throw ValidationException::withMessages([
                'salary_structure.deductions' => 'Deductions must be less than the total salary.',
            ]);
        }

        return [
            'basic_salary' => $basicSalary,
            'home_rent_percent' => $homeRentPercent,
            'home_rent_amount' => $homeRentAmount,
            'medical_percent' => $medicalPercent,
            'medical_amount' => $medicalAmount,
            'conveyance_percent' => $conveyancePercent,
            'conveyance_amount' => $conveyanceAmount,
            'other_allowances' => $otherAllowances,
            'deductions' => $deductions,
            'gross_salary' => $grossSalary,
        ];
    }

    private function percentageAmount(float $basicSalary, float $percentage): float
    {
        return round($basicSalary * $percentage / 100, 4);
    }
}
