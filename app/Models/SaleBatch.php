<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleBatch extends Model
{
    protected $fillable = [
        'batch_code',
        'sale_id'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}