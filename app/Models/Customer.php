<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'account_id',
        'code',
        'name',
        'proprietor_name',
        'mobile',
        'email',
        'nid_number',
        'vat_reg_no',
        'tin_no',
        'trade_license',
        'discount_rate',
        'credit_limit',
        'credit_days',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_rate' => 'decimal:4',
            'credit_limit' => 'decimal:4',
            'credit_days' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditSaleAllocations(): HasMany
    {
        return $this->hasMany(CreditSaleCustomer::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(PartyOpeningBalance::class);
    }
}
