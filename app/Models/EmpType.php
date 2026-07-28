<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(EmpDepartment::class, 'emp_type_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'emp_type_id');
    }
}
