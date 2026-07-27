<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'account_id',
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
        'highest_education',
        'reference_one_name',
        'reference_one_phone',
        'reference_one_address',
        'reference_two_name',
        'reference_two_phone',
        'reference_two_address'
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'status_date' => 'date',
        'status' => 'boolean'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function empType()
    {
        return $this->belongsTo(EmpType::class);
    }

    public function department()
    {
        return $this->belongsTo(EmpDepartment::class);
    }

    public function designation()
    {
        return $this->belongsTo(EmpDesignation::class);
    }

    public function dailyOtherProductSales()
    {
        return $this->hasMany(DailyOtherProductSale::class);
    }
}
