<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'payroll_period_id',
        'payroll_snapshot_id',
        'employee_id',
        'monthly_salary',
        'gross_salary',
        'net_salary',
        'total_deduction',
        'total_bonus',
        'advance_balance',
        'advance_applied',
        'salary_payable',
        'loan_balance',
        'net_payable',
        'advance_adjustment_voucher_id',
        'payment_voucher_id',
        'employee_salary_payment_id',
        'status',
        'processed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:4',
            'gross_salary' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'total_deduction' => 'decimal:4',
            'total_bonus' => 'decimal:4',
            'advance_balance' => 'decimal:4',
            'advance_applied' => 'decimal:4',
            'salary_payable' => 'decimal:4',
            'loan_balance' => 'decimal:4',
            'net_payable' => 'decimal:4',
            'processed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PayrollSnapshot::class, 'payroll_snapshot_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'payment_voucher_id');
    }

    public function advanceAdjustmentVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'advance_adjustment_voucher_id');
    }

    public function salaryPayment(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryPayment::class, 'employee_salary_payment_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(PayrollExtra::class);
    }

    public function voucherLinks(): HasMany
    {
        return $this->hasMany(PayrollVoucherLink::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
