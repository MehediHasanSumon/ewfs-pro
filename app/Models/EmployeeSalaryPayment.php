<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryPayment extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'employee_id',
        'payment_voucher_id',
        'voucher_transaction_type_id',
        'salary_month',
        'salary_year',
        'amount',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'integer',
            'salary_year' => 'integer',
            'amount' => 'decimal:4',
        ];
    }

    public function scopeForPeriod(
        Builder $query,
        int $month,
        int $year
    ): Builder {
        return $query
            ->where('salary_month', $month)
            ->where('salary_year', $year);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'payment_voucher_id');
    }

    public function voucherTransactionType(): BelongsTo
    {
        return $this->belongsTo(VoucherTransactionType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
