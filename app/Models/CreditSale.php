<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class CreditSale extends Model
{
    protected $appends = [
        'customer_id',
        'vehicle_id',
        'product_id',
        'category_code',
        'purchase_price',
        'quantity',
        'amount',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'type',
        'product',
        'vehicle',
    ];

    protected $fillable = [
        'shift_id',
        'sale_date',
        'sale_time',
        'invoice_no',
        'memo_no',
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
            'sale_date' => 'date',
            'grand_total' => 'decimal:4',
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

    public function customers(): HasMany
    {
        return $this->hasMany(CreditSaleCustomer::class);
    }

    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            CreditSaleCustomer::class,
            'credit_sale_id',
            'id',
            'id',
            'customer_id'
        );
    }

    public function getCustomerIdAttribute(): ?int
    {
        return $this->firstCustomerAllocation()?->customer_id;
    }

    public function getVehicleIdAttribute(): ?int
    {
        return $this->firstItem()?->vehicle_id;
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
        return (float) ($this->firstCustomerAllocation()?->subtotal ?? $this->grand_total);
    }

    public function getDiscountAttribute(): float
    {
        return (float) ($this->firstCustomerAllocation()?->discount_total ?? 0);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->grand_total;
    }

    public function getPaidAmountAttribute(): float
    {
        $allocation = $this->firstCustomerAllocation();

        return $allocation
            ? (float) $allocation->paymentAllocations()->sum('amount')
            : 0;
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->paid_amount);
    }

    public function getTypeAttribute(): string
    {
        return 'regular';
    }

    public function getProductAttribute(): ?Product
    {
        $item = $this->firstItem();

        return $item?->relationLoaded('product')
            ? $item->product
            : $item?->product()->first();
    }

    public function getVehicleAttribute(): ?Vehicle
    {
        $item = $this->firstItem();

        return $item?->relationLoaded('vehicle')
            ? $item->vehicle
            : $item?->vehicle()->first();
    }

    private function firstCustomerAllocation(): ?CreditSaleCustomer
    {
        return $this->relationLoaded('customers')
            ? $this->customers->first()
            : $this->customers()->first();
    }

    private function firstItem(): ?CreditSaleItem
    {
        $allocation = $this->firstCustomerAllocation();

        if (! $allocation) {
            return null;
        }

        return $allocation->relationLoaded('items')
            ? $allocation->items->first()
            : $allocation->items()->with('category')->first();
    }
}
