<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'customer_id',
        'vehicle_type',
        'vehicle_name',
        'vehicle_number',
        'reg_date',
        'status'
    ];

    protected $casts = [
        'reg_date' => 'date',
        'status' => 'boolean'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'vehicle_products');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}