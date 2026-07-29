<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryStructure extends Model
{
    protected $fillable = [
        'basic_salary',
        'home_rent_percent',
        'home_rent_amount',
        'medical_percent',
        'medical_amount',
        'conveyance_percent',
        'conveyance_amount',
        'other_allowances',
        'deductions',
        'gross_salary',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:4',
            'home_rent_percent' => 'decimal:4',
            'home_rent_amount' => 'decimal:4',
            'medical_percent' => 'decimal:4',
            'medical_amount' => 'decimal:4',
            'conveyance_percent' => 'decimal:4',
            'conveyance_amount' => 'decimal:4',
            'other_allowances' => 'decimal:4',
            'deductions' => 'decimal:4',
            'gross_salary' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
