<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'status'];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function dailyOtherProductSales()
    {
        return $this->hasMany(DailyOtherProductSale::class);
    }
}