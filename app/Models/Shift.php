<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'display_order', 'status'];

    protected $casts = [
        'display_order' => 'integer',
        'status' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(ShiftClosing::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
