<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpType extends Model
{
    protected $fillable = [
        'name',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function departments()
    {
        return $this->hasMany(EmpDepartment::class, 'emp_type_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'emp_type_id');
    }
}
