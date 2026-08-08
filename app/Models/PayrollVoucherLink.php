<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollVoucherLink extends Model
{
    public const ROLE_SALARY = 'salary';

    public const ROLE_EXTRA = 'extra';

    public const ROLE_ADVANCE_ADJUSTMENT = 'advance_adjustment';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'payroll_item_id',
        'payroll_extra_id',
        'voucher_id',
        'role',
        'status',
    ];

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function payrollExtra(): BelongsTo
    {
        return $this->belongsTo(PayrollExtra::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
