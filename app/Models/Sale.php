<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Sale extends Model
{
    protected $appends = [
        'customer',
        'mobile_number',
        'vehicle_no',
        'product_id',
        'category_code',
        'purchase_price',
        'quantity',
        'amount',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'mobile_no',
    ];

    protected $fillable = [
        'shift_id',
        'customer_id',
        'vehicle_id',
        'journal_entry_id',
        'sale_type',
        'sale_date',
        'sale_time',
        'invoice_no',
        'memo_no',
        'customer_name_snapshot',
        'customer_mobile_snapshot',
        'customer_address_snapshot',
        'company_name_snapshot',
        'proprietor_name_snapshot',
        'vehicle_number_snapshot',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'status',
        'is_send_sms',
        'remarks',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'is_send_sms' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->whereIn('status', ['posted', 'partially_paid', 'paid']);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('line_no');
    }

    public function products(): HasMany
    {
        return $this->items();
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SalePaymentAllocation::class);
    }

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(SalePaymentDetail::class);
    }

    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            SaleItem::class,
            'sale_id',
            'id',
            'id',
            'product_id'
        );
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(JournalLine::class, 'journal_entry_id', 'journal_entry_id')
            ->where('debit_amount', '>', 0)
            ->orderBy('line_no')
            ->with(['account', 'entry']);
    }

    public function getCustomerAttribute(): ?string
    {
        return $this->customer_name_snapshot;
    }

    public function getMobileNumberAttribute(): ?string
    {
        return $this->customer_mobile_snapshot;
    }

    public function getMobileNoAttribute(): ?string
    {
        return $this->customer_mobile_snapshot;
    }

    public function getVehicleNoAttribute(): ?string
    {
        return $this->vehicle_number_snapshot;
    }

    public function getProductIdAttribute(): ?int
    {
        return $this->firstItem()?->product_id;
    }

    public function getCategoryCodeAttribute(): ?string
    {
        $item = $this->firstItem();

        return $item?->relationLoaded('category')
            ? $item->category?->code
            : $item?->category()->value('code');
    }

    public function getPurchasePriceAttribute(): float
    {
        return (float) ($this->firstItem()?->unit_cost ?? 0);
    }

    public function getQuantityAttribute(): float
    {
        return (float) ($this->firstItem()?->quantity ?? 0);
    }

    public function getAmountAttribute(): float
    {
        return (float) $this->subtotal;
    }

    public function getDiscountAttribute(): float
    {
        return (float) $this->discount_total;
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->grand_total;
    }

    public function getPaidAmountAttribute(): float
    {
        if ($this->status === 'paid') {
            return (float) $this->grand_total;
        }

        return (float) $this->paymentAllocations()->sum('amount');
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->paid_amount);
    }

    private function firstItem(): ?SaleItem
    {
        return $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->with('category')->first();
    }
}
