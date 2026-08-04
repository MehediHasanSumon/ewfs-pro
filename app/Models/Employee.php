<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'account_id',
        'payment_account_id',
        'user_id',
        'emp_type_id',
        'department_id',
        'designation_id',
        'employee_code',
        'employee_name',
        'email',
        'order',
        'dob',
        'gender',
        'blood_group',
        'marital_status',
        'religion',
        'emergency_contact_person',
        'nid',
        'mobile',
        'mobile_two',
        'emergency_contact_number',
        'father_name',
        'mother_name',
        'present_address',
        'permanent_address',
        'job_status',
        'salary',
        'joining_date',
        'status',
        'status_date',
        'photo',
        'signature',
        'nid_document_path',
        'highest_education',
        'reference_one_name',
        'reference_one_phone',
        'reference_one_address',
        'reference_two_name',
        'reference_two_phone',
        'reference_two_address',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'joining_date' => 'date',
            'status_date' => 'date',
            'salary' => 'decimal:4',
            'status' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empType(): BelongsTo
    {
        return $this->belongsTo(EmpType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(EmpDepartment::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(EmpDesignation::class);
    }

    public function salaryStructure(): HasOne
    {
        return $this->hasOne(EmployeeSalaryStructure::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryPayment::class);
    }

    public function shiftClosingProductItems(): HasMany
    {
        return $this->hasMany(ShiftClosingProductItem::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
