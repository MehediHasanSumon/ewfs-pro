<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $fillable = [
        'group_id',
        'ac_number',
        'name',
        'semantic_code',
        'currency',
        'is_control_account',
        'allow_manual_posting',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_control_account' => 'boolean',
            'allow_manual_posting' => 'boolean',
            'is_system' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function dailyBalances(): HasMany
    {
        return $this->hasMany(AccountDailyBalance::class);
    }
}
