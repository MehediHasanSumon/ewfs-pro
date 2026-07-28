<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditSaleCustomer extends Model
{
    protected $fillable = [
        'credit_sale_id',
        'customer_id',
        'journal_entry_id',
        'customer_name_snapshot',
        'customer_mobile_snapshot',
        'subtotal',
        'discount_total',
        'grand_total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
        ];
    }

    public function creditSale(): BelongsTo
    {
        return $this->belongsTo(CreditSale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditSaleItem::class)->orderBy('line_no');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CreditSalePaymentAllocation::class);
    }
}
