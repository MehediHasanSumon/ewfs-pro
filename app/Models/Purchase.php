<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Purchase extends Model
{
    protected $appends = [
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'net_total_amount',
        'paid_amount',
        'due_amount',
        'from_account_id',
    ];

    protected $fillable = [
        'supplier_id',
        'shift_id',
        'journal_entry_id',
        'invoice_no',
        'supplier_invoice_no',
        'memo_no',
        'purchase_date',
        'purchase_time',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'status',
        'remarks',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->whereIn('status', ['posted', 'partially_paid', 'paid']);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('line_no');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }

    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            PurchaseItem::class,
            'purchase_id',
            'id',
            'id',
            'product_id'
        );
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(JournalLine::class, 'journal_entry_id', 'journal_entry_id')
            ->where('credit_amount', '>', 0)
            ->whereNull('supplier_id')
            ->orderBy('line_no')
            ->with(['account', 'entry']);
    }

    public function fromAccount(): HasOneThrough
    {
        return $this->hasOneThrough(
            Account::class,
            JournalLine::class,
            'journal_entry_id',
            'id',
            'journal_entry_id',
            'account_id'
        )->where('journal_lines.credit_amount', '>', 0)
            ->whereNull('journal_lines.supplier_id')
            ->orderBy('journal_lines.line_no');
    }

    public function getProductIdAttribute(): ?int
    {
        return $this->firstItem()?->product_id;
    }

    public function getQuantityAttribute(): float
    {
        return (float) ($this->firstItem()?->quantity ?? 0);
    }

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->firstItem()?->unit_cost ?? 0);
    }

    public function getDiscountAttribute(): float
    {
        return (float) $this->discount_total;
    }

    public function getNetTotalAmountAttribute(): float
    {
        return (float) $this->grand_total;
    }

    public function getPaidAmountAttribute(): float
    {
        $directPayment = $this->relationLoaded('journalEntry')
            ? (float) $this->journalEntry?->lines->where('supplier_id', $this->supplier_id)->sum('debit_amount')
            : (float) JournalLine::query()
                ->where('journal_entry_id', $this->journal_entry_id)
                ->where('supplier_id', $this->supplier_id)
                ->sum('debit_amount');

        return $directPayment + (float) $this->paymentAllocations()->sum('amount');
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->paid_amount);
    }

    public function getFromAccountIdAttribute(): ?int
    {
        return $this->relationLoaded('transaction')
            ? $this->transaction?->account_id
            : $this->transaction()->value('account_id');
    }

    private function firstItem(): ?PurchaseItem
    {
        return $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->first();
    }
}
