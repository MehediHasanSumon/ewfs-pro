<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'name',
        'ac_number',
        'group_id',
        'group_code',
        'due_amount',
        'paid_amount',
        'total_amount',
        'status'
    ];

    protected $casts = [
        'due_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => 'boolean'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_code', 'code');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'account_id');
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }
}
