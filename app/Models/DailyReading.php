<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReading extends Model
{
    protected $fillable = [
        'date',
        'shift_id',
        'employee_id',
        'credit_sales',
        'bank_sales',
        'cash_sales',
        'credit_sales_other',
        'bank_sales_other',
        'cash_sales_other',
        'cash_receive',
        'bank_receive',
        'total_cash',
        'cash_payment',
        'bank_payment',
        'office_payment',
        'final_due_amount'
    ];

    protected $casts = [
        'credit_sales' => 'decimal:2',
        'bank_sales' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'credit_sales_other' => 'decimal:2',
        'bank_sales_other' => 'decimal:2',
        'cash_sales_other' => 'decimal:2',
        'cash_receive' => 'decimal:2',
        'bank_receive' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'cash_payment' => 'decimal:2',
        'bank_payment' => 'decimal:2',
        'office_payment' => 'decimal:2',
        'final_due_amount' => 'decimal:2'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
