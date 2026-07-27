<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpDesignation extends Model
{
    protected $fillable = [
        'name',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }
}
