<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleProduct extends Model
{
    protected $fillable = [
        'vehicle_id',
        'product_id'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
