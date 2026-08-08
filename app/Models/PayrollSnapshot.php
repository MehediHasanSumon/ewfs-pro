<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class PayrollSnapshot extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'department_id',
        'designation_id',
        'employee_name',
        'employee_code',
        'department_name',
        'designation_name',
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
        'net_salary',
        'payment_account_id',
        'payment_method',
        'snapshot_hash',
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
            'net_salary' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw ValidationException::withMessages([
                'payroll_snapshot' => 'Payroll salary snapshots are immutable.',
            ]);
        });

        static::deleting(function (): never {
            throw ValidationException::withMessages([
                'payroll_snapshot' => 'Payroll salary snapshots cannot be deleted.',
            ]);
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function item(): HasOne
    {
        return $this->hasOne(PayrollItem::class);
    }
}
