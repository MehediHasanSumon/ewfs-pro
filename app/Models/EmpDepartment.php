<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpDepartment extends Model
{
    protected $fillable = [
        'emp_type_id',
        'name',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function empType()
    {
        return $this->belongsTo(EmpType::class, 'emp_type_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
