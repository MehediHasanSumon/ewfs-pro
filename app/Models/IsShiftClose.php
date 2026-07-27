<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsShiftClose extends Model
{
    protected $fillable = ['close_date', 'shift_id'];

    protected $casts = [
        'close_date' => 'date',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
