<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'mobile',
        'email',
        'address',
        'proprietor_name',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
